#!/usr/bin/php
<?php
// Comportamiento.
ini_set('error_reporting',E_ERROR);
ini_set('display_errors','On');
ini_set('date.timezone','Europe/Madrid');
setlocale(LC_TIME,'es_ES.UTF-8');

// Configuracion.
require_once 'configuracion.php';

// Auxiliar.
require_once 'nucleo.php';

// Opciones.

$opciones=getopt(implode('',array(
	't', // Asentar totales.
	'v', // Informar.
	'd', // Depurar.
	'f:', // fecha.
)));

// Conexion base datos.

if(!mysql_connect(PANDORA_MYSQL_MAQUINA, PANDORA_MYSQL_USUARIO, PANDORA_MYSQL_CLAVE)){
	die('c');
}

if(!mysql_select_db(PANDORA_MYSQL_BASEDATOS)){
	die('db');
}

// Comportamiento base datos.

$zona_horaria=substr_replace(strftime('%z'),':',3, 0);

$resultado=mysql_query('SET time_zone = "'.$zona_horaria.'"');

if(!$resultado){
	die('tz');
}

// Obtencion de valores medios del dia por sensor y hora.

$dia_instante=$opciones['f']? strtotime($opciones['f']): time();

$dia_aaaammdd=strftime('%F',$dia_instante);

$consulta='
SELECT
tm.nombre AS nombre,
-- td.id_agente_modulo,
td.datos AS valor,
AVG(td.datos) AS media,
-- FROM_UNIXTIME(td.utimestamp) AS instante,
CONVERT(FROM_UNIXTIME(td.utimestamp,"%k"),DECIMAL) AS hora,
CONVERT(FROM_UNIXTIME(UNIX_TIMESTAMP(),"%k"),DECIMAL) AS hora_actual,
IF(CONVERT(FROM_UNIXTIME(td.utimestamp,"%k"),DECIMAL) = CONVERT(FROM_UNIXTIME(UNIX_TIMESTAMP(),"%k"),DECIMAL),(MAX(td.utimestamp) % 3600)/3600,1) AS horas
FROM tagente_datos AS td
INNER JOIN tagente_modulo AS tm ON tm.id_agente_modulo = td.id_agente_modulo
WHERE tm.nombre IN ("'.SENSORES_TERMICA_CAUDAL_NOMBRE.'","'.SENSORES_TERMICA_TEMPERATURA_ENTRADA_NOMBRE.'","'.SENSORES_TERMICA_TEMPERATURA_SALIDA_NOMBRE.'","'.SENSORES_ELECTRICA_INTENSIDAD_1_NOMBRE.'","'.SENSORES_ELECTRICA_INTENSIDAD_2_NOMBRE.'") AND UNIX_TIMESTAMP("'.$dia_aaaammdd.'") <= td.utimestamp AND td.utimestamp < UNIX_TIMESTAMP(DATE_ADD("'.$dia_aaaammdd.'",INTERVAL 1 DAY))
GROUP BY tm.nombre, hora
ORDER BY tm.nombre ASC, hora ASC
';

$resultado=mysql_query($consulta);

if(!$resultado){
	die('q');
}

while($fila=mysql_fetch_assoc($resultado)){
	$datos[$fila['nombre']][$fila['hora']]=array(
		'valor'=>$fila['valor'],
		'horas'=>$fila['horas'],
	);
}

//~ echo print_r($datos,true);

// Termica.

for($termica=0, $hora=0,$horas=24; $hora<=$horas; $hora++){
	// .
	
	$caudal=$datos[SENSORES_TERMICA_CAUDAL_NOMBRE][$hora]['valor']; // [litros · hora⁻¹] ≡ [kg · hora⁻¹].
	
	$delta_temperatura=$datos[SENSORES_TERMICA_TEMPERATURA_SALIDA_NOMBRE][$hora]['valor']-$datos[SENSORES_TERMICA_TEMPERATURA_ENTRADA_NOMBRE][$hora]['valor']; // [℃].
	
	$rendimiento=TERMICA_RENDIMIENTO; // [Adimensional].
	
	$tiempo=1; // [hora].
	$tiempo=$datos[SENSORES_TERMICA_CAUDAL_NOMBRE][$hora]['horas']; // [hora].
	
	$calor_especifico=4.168; // 4168 [J · kg⁻¹ · ℃⁻¹] · 0.001 [kJ · J⁻¹] = 4.168 [kJ⁻ · kg⁻¹ · ℃⁻¹].
	
	$kJ__kwh=0.00027777; // [kJ⁻¹ · kWh].
	
	// .
	$termica_hora=$caudal*$delta_temperatura*$tiempo*$calor_especifico*$rendimiento*$kJ__kwh; // [kWh].
	
	// .
	if($termica_hora<0){
		continue;
	}
	
	// .
	$termica+=$termica_hora;
}

valor_registrar(REGISTRO_RAIZ.strftime('energia/%Y/%m/energia_termica_%Y-%m-%d.txt',$dia_instante),$termica);

echo $termica,NL;

// Electrica.

for($electrica=0, $hora=0,$horas=24; $hora<=$horas; $hora++){
	// 1.
	
	$voltaje_1=750; // [V].
	
	$intensidad_1=$datos[SENSORES_ELECTRICA_INTENSIDAD_1_NOMBRE][$hora]['valor']; // [A].
	
	//~ $tiempo_1=1; // [hora].
	$tiempo_1=$datos[SENSORES_ELECTRICA_INTENSIDAD_1_NOMBRE][$hora]['horas']; // [hora].
	
	$wh__kwh=0.001; // 1000⁻¹ [kWh · Wh⁻¹].
	
	// 2.
	
	$voltaje_2=750; // [V].
	
	$intensidad_2=$datos[SENSORES_ELECTRICA_INTENSIDAD_2_NOMBRE][$hora]['valor']; // [A].
	
	//~ $tiempo_2=1; // [hora].
	$tiempo_2=$datos[SENSORES_ELECTRICA_INTENSIDAD_2_NOMBRE][$hora]['horas']; // [hora].
	
	$wh__kwh=0.001; // 1000⁻¹ [kWh · Wh⁻¹].
	
	// .
	$electrica_hora=$voltaje_1*$intensidad_1*$tiempo_1*$wh__kwh+$voltaje_2*$intensidad_2*$tiempo_2*$wh__kwh;
	
	// .
	$electrica+=$electrica_hora;
}

valor_registrar(REGISTRO_RAIZ.strftime('energia/%Y/%m/energia_electrica_%Y-%m-%d.txt',$dia_instante),$electrica);

echo $electrica,NL;

// Asentar totales.

if(isset($opciones['t'])){
	registro_asentar_totales($dia_instante,array(
		'general'=>REGISTRO_RAIZ.'energia/energia_termica.txt',
		'aaaa'=>REGISTRO_RAIZ.'energia/%1$04d/energia_termica_%1$04d.txt',
		'mm'=>REGISTRO_RAIZ.'energia/%1$04d/%2$02d/energia_termica_%1$04d-%2$02d.txt',
		'dd'=>REGISTRO_RAIZ.'energia/%1$04d/%2$02d/energia_termica_%1$04d-%2$02d-%3$02d.txt',
	));
	
	registro_asentar_totales($dia_instante,array(
		'general'=>REGISTRO_RAIZ.'energia/energia_electrica.txt',
		'aaaa'=>REGISTRO_RAIZ.'energia/%1$04d/energia_electrica_%1$04d.txt',
		'mm'=>REGISTRO_RAIZ.'energia/%1$04d/%2$02d/energia_electrica_%1$04d-%2$02d.txt',
		'dd'=>REGISTRO_RAIZ.'energia/%1$04d/%2$02d/energia_electrica_%1$04d-%2$02d-%3$02d.txt',
	));
}


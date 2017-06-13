<?php

function valor_recuperar($ruta,$ajuste=null){
	// Comprobamos si existe el documento con el valor.
	if(!file_exists($ruta)){
		if(is_numeric($ajuste)){
			return $ajuste;
		}
		
		return 0;
	}
	
	// Recuperamos los valores registrados linea alinea.
	$valores=file($ruta,FILE_IGNORE_NEW_LINES);
	
	// Anotamos el valor principal.
	$valor=floatval($valores[0]);
	
	// Si hay un ajuste almacenado y no se ajusta explicitamente anotamos el desvio.
	if($ajuste!==false && $ajuste!==0 && is_numeric($valores[1])){
		$ajuste=intval($valores[1]);
	}
	
	// Comprobamos si hay que realizar un ajuste en el valor.
	if(is_numeric($ajuste)){
		$valor+=$ajuste;
	}
	
	// Devolvemos el valor.
	return $valor;
}

function valor_magnitud($valor,&$unidad,$magnitudes,$umbral=1000000,$formato='%1.0f'){
	switch($magnitudes){
		case 'e':{
			$magnitudes=array('kWh'=>1,'MWh'=>1000,'GWh'=>1000);
			
			$umbral=1000000;
			
			break;
		}
		case 'co2':{
			$magnitudes=array('kg'=>1,'Tn'=>1000);
			
			$umbral=1000;
			
			break;
		}
	}
	
	foreach($magnitudes as $magnitud_unidad=>$magnitud_factor){
		if($valor<$umbral){
			if(!$unidad){
				$unidad=$magnitud_unidad;
			}
			
			break;
		}
		
		$unidad=$magnitud_unidad;
		
		$valor/=$magnitud_factor;
	}
	
	$numero=sprintf($formato,$valor);
	
	return $numero;
}

function valor_registrar($ruta,$valor){
	if(file_exists($ruta)){
		$registro=file($ruta,FILE_IGNORE_NEW_LINES);
		
		$registro_ajuste=$registro[1];
	}
	
	$registro=array($valor);
	
	if($registro_ajuste){
		$registro[1]=$registro_ajuste;
	}
	
	$directorio=dirname($ruta);
	
	if(!is_dir($directorio)){
		mkdir($directorio,0777,true);
	}
	
	file_put_contents($ruta,implode("\n",$registro)."\n");
}

function registro_asentar_totales($instante,$periodos_ruta){
	list($instante_aaaa,$instante_mm,$instante_dd)=explode('-',strftime('%Y-%m-%d',$instante));
	
	// Dias mes.
	
	for($valor=0, $dia=1, $dias=intval($instante_dd); $dia<=$dias; $dia++){
		$registro_ruta=sprintf($periodos_ruta['dd'],$instante_aaaa,$instante_mm,$dia);
		
		$valor+=valor_recuperar($registro_ruta);
	}
	
	$registro_ruta=sprintf($periodos_ruta['mm'],$instante_aaaa,$instante_mm);
	
	valor_registrar($registro_ruta,$valor);
	
	// Meses año.
	
	for($valor=0, $mes=1,$meses=intval($instante_mm); $mes<=$meses; $mes++){
		$registro_ruta=sprintf($periodos_ruta['mm'],$instante_aaaa,$mes);
		
		$valor+=valor_recuperar($registro_ruta);
	}
	
	$registro_ruta=sprintf($periodos_ruta['aaaa'],$instante_aaaa);
	
	valor_registrar($registro_ruta,$valor);
	
	// Años general.
	
	for($valor=0, $año=2017,$años=intval($instante_aaaa); $año<=$años; $año++){
		$registro_ruta=sprintf($periodos_ruta['aaaa'],$año);
		
		$valor+=valor_recuperar($registro_ruta);
	}
	
	$registro_ruta=sprintf($periodos_ruta['general']);
	
	valor_registrar($registro_ruta,$valor);
}


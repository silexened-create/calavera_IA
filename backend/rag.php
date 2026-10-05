<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

/**
 * rag.php
 * Sistema RAG (Retrieval-Augmented Generation) simple basado en keywords.
 * Busca en knowledge.json las calaveras, rimas y datos más relevantes
 * para enriquecer el contexto enviado al modelo de IA.
 */

/**
 * Carga el archivo knowledge.json y devuelve su contenido como array.
 *
 * @return array|null Los datos del knowledge base o null si hay error.
 */
function cargar_conocimiento() {
    $ruta = __DIR__ . '/../data/knowledge.json';

    if (!file_exists($ruta)) {
        return null;
    }

    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $datos;
}

/**
 * Calcula la relevancia de un item basándose en coincidencia de palabras clave.
 *
 * @param string $consulta La pregunta del usuario.
 * @param array  $palabras_clave Las palabras clave del item.
 * @return int Puntuación de relevancia (mayor = más relevante).
 */
function calcular_relevancia($consulta, $palabras_clave) {
    $consulta_lower = mb_strtolower($consulta, 'UTF-8');
    $puntuacion = 0;

    foreach ($palabras_clave as $palabra) {
        $palabra_lower = mb_strtolower($palabra, 'UTF-8');
        if (mb_strpos($consulta_lower, $palabra_lower) !== false) {
            $puntuacion++;
        }
    }

    return $puntuacion;
}

/**
 * Busca el contexto más relevante en el knowledge base para una consulta dada.
 *
 * @param string $consulta La pregunta del usuario.
 * @return string Contexto formateado para inyectar en el prompt del modelo.
 */
function buscar_contexto($consulta) {
    $datos = cargar_conocimiento();

    if ($datos === null) {
        return '';
    }

    $resultados = [];

    // Buscar en calaveras
    if (!empty($datos['calaveras'])) {
        foreach ($datos['calaveras'] as $calavera) {
            $score = calcular_relevancia($consulta, $calavera['palabras_clave'] ?? []);
            if ($score > 0) {
                $resultados[] = [
                    'tipo'  => 'calavera',
                    'score' => $score,
                    'titulo' => $calavera['titulo'] ?? '',
                    'texto' => $calavera['texto'] ?? ''
                ];
            }
        }
    }

    // Buscar en rimas
    if (!empty($datos['rimas'])) {
        foreach ($datos['rimas'] as $rima) {
            $score = calcular_relevancia($consulta, $rima['palabras_clave'] ?? []);
            if ($score > 0) {
                $resultados[] = [
                    'tipo'  => 'rima',
                    'score' => $score,
                    'titulo' => $rima['titulo'] ?? '',
                    'texto' => $rima['texto'] ?? ''
                ];
            }
        }
    }

    // Buscar en datos
    if (!empty($datos['datos'])) {
        foreach ($datos['datos'] as $dato) {
            $score = calcular_relevancia($consulta, $dato['palabras_clave'] ?? []);
            if ($score > 0) {
                $resultados[] = [
                    'tipo'  => 'dato',
                    'score' => $score,
                    'titulo' => '',
                    'texto' => $dato['dato'] ?? ''
                ];
            }
        }
    }

    // Ordenar por relevancia descendente
    usort($resultados, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Tomar los 3 mejores resultados
    $top = array_slice($resultados, 0, 3);

    if (empty($top)) {
        return '';
    }

    // Formatear contexto para el prompt
    $contexto = "\n\n--- CONTEXTO DE LA BASE DE CONOCIMIENTO ---\n";
    foreach ($top as $item) {
        $tipo_label = strtoupper($item['tipo']);
        $titulo = $item['titulo'] ? " ({$item['titulo']})" : '';
        $contexto .= "[{$tipo_label}{$titulo}]: {$item['texto']}\n";
    }
    $contexto .= "--- FIN DEL CONTEXTO ---\n";

    return $contexto;
}

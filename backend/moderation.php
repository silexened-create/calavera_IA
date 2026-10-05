<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

/**
 * moderation.php
 * Filtro de moderación básico para contenido inseguro o inapropiado.
 * Devuelve un mensaje alternativo seguro si se detecta contenido prohibido.
 */

function normalize_accents($string) {
    $replacements = [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
        'ü'=>'u', 'Ü'=>'U'
    ];
    return strtr($string, $replacements);
}

/**
 * Verifica si el texto del usuario contiene palabras prohibidas.
 *
 * @param string $texto El texto a moderar.
 * @return array ['seguro' => bool, 'mensaje' => string|null]
 */
function moderar_contenido($texto) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    $texto_norm = normalize_accents($texto_lower);

    // Categorías de palabras prohibidas (usando regex para evitar falsos positivos)
    $categorias = [
        'insults' => [
            'pendej[oa]s?', 'put[oa]s?', 'chingad[oa]s?', 'chingar?', 
            'verg[ae]s?', 'mierdas?', 'culer[oa]s?', 'cabron(es)?', 'cabron[as]?', 
            'jotos?', 'maricas?', 'pinches?', 'mamon(es)?', 'mamon[as]?', 'idiotas?', 
            'estupid[oa]s?', 'imbecil(es)?'
        ],
        'violence' => [
            'matar', 'suicid\w*', 'drogas?', 'armas?', 'asesin\w*'
        ],
        'sexual' => [
            'sexo', 'porno\w*', 'desnud\w*', 'cogiendo', 'follar'
        ],
        'hate' => [
            'nazi\w*', 'racis\w*', 'odio racial'
        ]
    ];

    $mensajes_categorias = [
        'insults' => "Con palabras altisonantes me quieres hablar,\npero en el Mictlán el respeto debes guardar.\nLimpia tu boca de esa grosería,\no la Calavera te ignorará todo el día.",
        'violence' => "De violencia y muerte cruel no quiero saber,\nla paz del camposanto no debes romper.\nBusca otro tema que no cause espanto,\no te quedarás solo en este camposanto.",
        'sexual' => "Esos temas carnales déjalos atrás,\nque a los puros huesos no los tentarás.\nGuarda el decoro, mortal atrevido,\no serás por siempre en el olvido.",
        'hate' => "El odio y el desprecio no cruzan el puente,\nen el Día de Muertos somos todos igual gente.\nDeja tu veneno fuera del altar,\no conmigo jamás volverás a charlar."
    ];

    foreach ($categorias as $categoria => $patrones) {
        foreach ($patrones as $patron) {
            if (preg_match('/\b' . $patron . '\b/iu', $texto_norm, $matches)) {
                error_log("MODERATION: category=$categoria word=" . $matches[0]);
                return [
                    'seguro' => false,
                    'mensaje' => $mensajes_categorias[$categoria]
                ];
            }
        }
    }

    // Restricción suave de temática
    $allowed_keywords = [
        'mexic', 'historia', 'cultura', 'tradicion', 'mictlan',
        'catrina', 'ofrenda', 'cempasuchil', 'muert', 'calaver',
        'azteca', 'maya', 'prehispanic', 'colonial', 'españ', 'conquist',
        'pan', 'altar', 'noviembre', 'poesia', 'verso', 'rima',
        'vida', 'alma', 'espiritu', 'leyend', 'dios', 'nahual', 'xolo',
        'hola', 'salud', 'quien', 'como', 'nombre', 'hueso', 'tumb',
        'panteon', 'flor', 'vela', 'copal', 'incienso', 'inframundo',
        'dia', 'fiesta', 'celebracion', 'origen', 'epoca', 'virreina',
        'indigena', 'mito', 'cuento', 'costumbre', 'garbancer', 'posada',
        'rivera', 'diego', 'frida', 'arte', 'dibuj', 'grabad', 'papel', 'picado',
        'mesoamerica', 'tlatoani', 'tenochtitlan', 'cruz', 'rez', 'lloron',
        'cancion', 'musica', 'mariachi'
    ];

    $allowed = false;
    foreach ($allowed_keywords as $kw) {
        if (mb_strpos($texto_norm, $kw) !== false) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed && mb_strlen(trim($texto_norm)) > 0) {
        return [
            'seguro' => false,
            'mensaje' => "De temas extraños vienes a parlar,\npero de eso la Calavera no quiere hablar.\nHáblame de México, historia o tradición,\ny con gusto alegraré tu corazón."
        ];
    }

    // Verificar si el mensaje es demasiado largo (posible spam)
    if (mb_strlen($texto) > 500) {
        return [
            'seguro' => false,
            'mensaje' => "Tu mensaje es muy extenso, amigo,\n"
                       . "la Calavera prefiere algo breve.\n"
                       . "Hazme una pregunta corta, te digo,\n"
                       . "y mi verso a ti se atreve."
        ];
    }

    // Verificar si el mensaje está vacío
    if (mb_strlen(trim($texto)) === 0) {
        return [
            'seguro' => false,
            'mensaje' => "No me has dicho nada, mortal,\n"
                       . "mi oído de hueso está esperando.\n"
                       . "Dime algo, bien o mal,\n"
                       . "que la Calavera sigue rimando."
        ];
    }

    return ['seguro' => true, 'mensaje' => null];
}

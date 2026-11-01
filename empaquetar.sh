#!/bin/bash

# 1. Obtener la ruta del directorio donde está guardado este archivo .sh
DIR_ACTUAL="$(cd "$(dirname "$0")" && pwd)"

# 2. Definir el nombre de la nueva carpeta
NOMBRE_CARPETA="bridgeprestashop"
RUTA_DESTINO="$DIR_ACTUAL/$NOMBRE_CARPETA"

# Limpiar si ya existe una carpeta anterior para empezar limpio
if [ -d "$RUTA_DESTINO" ]; then
    echo "Eliminando la carpeta anterior..."
    rm -rf "$RUTA_DESTINO"
fi

echo "Creando la carpeta: $NOMBRE_CARPETA..."
mkdir -p "$RUTA_DESTINO"

# 3. Copiar todo el contenido de la raíz hacia la nueva carpeta,
# excluyendo el propio script, la carpeta destino, archivos .zip y de control de versiones.
echo "Copiando archivos y carpetas del proyecto..."
for archivo in "$DIR_ACTUAL"/*; do
    nombre_base=$(basename "$archivo")
    
    # Evitamos copiarnos a nosotros mismos, zips, documentación interna y la carpeta de destino
    if [ "$nombre_base" != "$NOMBRE_CARPETA" ] && \
       [ "$nombre_base" != "empaquetar.sh" ] && \
       [ "$nombre_base" != "REGLAS_VALIDADOR_PRESTASHOP.md" ] && \
       [ "$nombre_base" != "EXPLICACION_ERRORES_VALIDADOR.md" ] && \
       [[ "$nombre_base" != *.zip ]]; then
        cp -r "$archivo" "$RUTA_DESTINO/"
    fi
done

# 4. Asegurar que todas las carpetas contengan un index.php (Regla de seguridad PrestaShop Validator)
echo "Verificando index.php en todas las carpetas..."
find "$RUTA_DESTINO" -type d | while read -r dir; do
    if [ ! -f "$dir/index.php" ]; then
        echo "Añadiendo index.php en: $dir"
        cat << 'EOF' > "$dir/index.php"
<?php
/**
 * 2026 Bridge PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @author    Luis Eduardo Alvarado Martínez <luis.alvaradomtz97@gmail.com>
 * @copyright 2026 Bridge PrestaShop
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;
EOF
    fi
done

# 5. Comprimir la carpeta recién creada en un archivo .zip en la misma ubicación
NOMBRE_ZIP="$DIR_ACTUAL/${NOMBRE_CARPETA}.zip"

# # Si ya existía un zip con el mismo nombre, lo borramos para crear uno nuevo limpio
if [ -f "$NOMBRE_ZIP" ]; then
    rm -f "$NOMBRE_ZIP"
fi

echo "Creando el archivo ZIP: $NOMBRE_ZIP..."
zip -r "$NOMBRE_ZIP" "$NOMBRE_CARPETA"

echo "¡Listo! Proceso terminado con éxito."
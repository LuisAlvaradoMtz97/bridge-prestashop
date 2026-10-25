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
# excluyendo el propio script y la carpeta de destino para evitar duplicados o bucles.
echo "Copiando archivos y carpetas del proyecto..."
for archivo in "$DIR_ACTUAL"/*; do
    # Obtenemos solo el nombre del archivo o carpeta
    nombre_base=$(basename "$archivo")
    
    # Evitamos copiarnos a nosotros mismos (empaquetar.sh) y la carpeta de destino (prestashop)
    if [ "$nombre_base" != "$NOMBRE_CARPETA" ] && [ "$nombre_base" != "empaquetar.sh" ]; then
        cp -r "$archivo" "$RUTA_DESTINO/"
    fi
done

# 4. Comprimir la carpeta recién creada en un archivo .zip en la misma ubicación
NOMBRE_ZIP="$DIR_ACTUAL/${NOMBRE_CARPETA}.zip"

# # Si ya existía un zip con el mismo nombre, lo borramos para crear uno nuevo limpio
if [ -f "$NOMBRE_ZIP" ]; then
    rm -f "$NOMBRE_ZIP"
fi

echo "Creando el archivo ZIP: $NOMBRE_ZIP..."
zip -r "$NOMBRE_ZIP" "$NOMBRE_CARPETA"

echo "¡Listo! Proceso terminado con éxito."
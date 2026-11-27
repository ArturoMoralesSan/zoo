# Optimización de imágenes (WebP): tarjetas y especies

Las imágenes de las tarjetas (`generated_image`, `thumbnail_image`, `artwork_image`) se
guardan en **WebP**. La app las descarga por `card_image_url` y `thumbnail_image`, y Viro
usa `card_image_url` como textura de la tarjeta 3D.

## Por qué WebP y no AVIF

| | WebP | AVIF |
| --- | --- | --- |
| `<Image>` de React Native en Android (Fresco) | sí | **no** |
| Texturas de Viro (Fresco en Android, UIImage en iOS) | sí (iOS 14+) | **no** en Android, iOS 16+ |
| expo-image | sí | sí |
| Codificar en el navegador | WASM (libwebp) en todos | ningún navegador codifica AVIF desde un canvas |
| Codificar en el servidor | GD e Imagick suelen tenerlo | requiere libavif/libheif |

AVIF rompería las texturas de Viro en Android, y solo pesa ~20-30 % menos que WebP.

## Perfiles

Compartidos entre PHP y TS en `resources/data/card-image-profiles.json`. Nunca se agranda
una imagen.

| Imagen | Máximo | Calidad | Motivo |
| --- | --- | --- | --- |
| `generated` | 1144 × 2032 | 88 | 2× la plantilla, igual que la textura del visor 3D |
| `thumbnail` | 800 × 800 | 82 | cuadrícula de la app |
| `artwork` | 2048 × 2048 | 90 | alimenta futuros renders de la tarjeta |

Medido el 2026-11-10 con una tarjeta real: PNG de 1716 × 3048 y **5,98 MB → 215-226 KB**,
sin diferencia visible a 100 % en texto, iconos ni esquinas transparentes. La VRAM de la
textura baja de ~21 MB a ~9 MB. El PNG a 3× sigue disponible con el botón de descarga del
editor.

## Quién codifica

1. **Navegador** (`lib/imageOptimize.ts`): libwebp en WASM (`@jsquash/webp`) con
   `use_sharp_yuv` y `method: 4` (~0,7 s por tarjeta; `method: 6` pesa ~5 % menos pero
   tarda ~6 s). Si el WASM no carga, usa `canvas.toBlob('image/webp')`. Si el navegador
   no produce WebP (Safari antiguo), envía el archivo original o un PNG a 2×.
2. **Servidor**, solo si puede (`App\Services\ImageEncoding`): Imagick con sharp YUV o, si
   no, GD (sin sharp YUV, el texto queda un poco más suave). Se elige con
   `IMAGE_ENCODER=auto|imagick|gd|none`. Al guardar, convierte los archivos que no llegaron
   en WebP o exceden su perfil. Si falla, guarda el original y escribe un `warning` en el
   log. Guardar nunca falla por la compresión.

Instalación en Debian/Ubuntu: `sudo apt install php8.4-imagick php8.4-gd` (o los
paquetes de tu versión de PHP), y luego comprobar:

```bash
php -r 'var_dump(in_array("WEBP", Imagick::queryFormats("WEBP")));'
php artisan cards:images:report   # muestra el codificador detectado
```

## Peso en el panel

Las tarjetas guardan en `cards.image_original_bytes` (`{ generated, thumbnail,
artwork }`, en bytes) el peso de cada imagen antes de comprimirla, porque los
originales no se conservan. El navegador envía `image_original_bytes[...]` al
guardar: el archivo elegido para la miniatura y la ilustración, y el mismo render
en PNG para la tarjeta generada. Sin ese dato, el servidor usa el peso del archivo
recibido. La herramienta de conversión registra el peso del original que reemplaza.
Al eliminar una imagen se olvida su peso.

`CardController::fileSizes()` entrega a Show y Edit `fileSizes.{generated,
thumbnail, artwork, model}` con `{ current, original }`. El componente
`SizeSavings.vue` muestra «original → en la app −N %». En los formularios, el peso
de una imagen nueva se calcula al elegirla; `optimizeUpload` guarda el resultado
para no codificarla dos veces al enviar.

## Convertir las imágenes ya guardadas

**Cada original se borra** en cuanto su WebP queda guardado. No hay forma de deshacerlo,
así que antes de convertir copia `storage/app/public/cards`.

- **Panel:** Administración → Recursos → Optimización (permiso `assets.optimize`). Procesa
  una imagen a la vez.
  Si el servidor tiene codificador, codifica él; si no, el navegador descarga, codifica y
  sube cada imagen.
- **Consola** (requiere Imagick o GD):

```bash
php artisan cards:images:report                  # solo lectura
php artisan cards:images:optimize --dry-run      # qué se convertiría
php artisan cards:images:optimize [--card=ID] [--image=generated|thumbnail|artwork] [--limit=N]
```

Ambos usan `CardImageOptimizer::replace()`, que:

1. Rechaza con 409 si la ruta guardada ya no es la que se optimizó (edición concurrente).
2. Rechaza con 422 si el resultado no es WebP, excede el perfil o **no pesa menos** que
   el original. Esa imagen queda pendiente, sin cambios.
3. Guarda el WebP y actualiza la columna dentro de una transacción con `lockForUpdate`.
4. Borra el original solo después del commit. Si algo falla, borra el WebP nuevo.

## Hosting

- El navegador necesita los `.wasm` de `public/build/assets`. Si el servidor no los envía
  como `application/wasm`, Emscripten recurre a una carga más lenta, pero funciona igual.
- Imagick tarda ~7 s por tarjeta a 3×; GD ~1 s. Ambos caben en el `max_execution_time`
  habitual, porque el panel procesa una imagen por petición.

## Especies

Las imágenes de especies (`species_images` de tipo `main`, `thumbnail` y `gallery`) usan el
mismo flujo: el navegador las comprime al elegirlas, el servidor las convierte al guardar si
puede, y las ya guardadas se convierten desde el panel o la consola. Perfiles en
`resources/data/species-image-profiles.json` (`config('images.species')`):

| Imagen | Máximo | Calidad | Motivo |
| --- | --- | --- | --- |
| `main` | 1600 × 1600 | 85 | pantalla completa del teléfono |
| `thumbnail` | 512 × 512 | 82 | listas y marcador del mapa |
| `gallery` | 1600 × 1600 | 85 | pantalla completa del teléfono |

- **Código:** `SpeciesImageOptimizer` (comparte con `CardImageOptimizer` el trait
  `ImageEncoding\OptimizesWebpUploads`), `composables/useSpeciesImageUploads.ts` y
  `optimizeImage()` de `lib/imageOptimize.ts`. El panel es el mismo componente que el de
  tarjetas (`components/admin/OptimizeImagesPanel.vue`).
- **Peso:** cada fila guarda `original_bytes`. El navegador envía
  `image_original_bytes[main|thumbnail]` y `gallery_original_bytes[i]` (por índice de
  `gallery_images`). Edit y Show reciben `bytes` (peso actual) en cada imagen.
- **Límite:** el servidor acepta hasta 5 MB ya comprimida; la galería deja elegir archivos de
  hasta 25 MB porque los comprime antes de subirlos.
- **Panel:** el mismo de Recursos → Optimización, debajo del de tarjetas.
- **Consola** (requiere Imagick o GD; **borra cada original**, copia antes
  `storage/app/public/species`):

```bash
php artisan species:images:report
php artisan species:images:optimize --dry-run
php artisan species:images:optimize [--species=ID] [--image=main|thumbnail|gallery] [--limit=N]
```

## Recorte y punto focal

Antes de comprimir, cada imagen elegida en el panel pasa por un diálogo de recorte
(`components/admin/ImageCropDialog.vue`, cropperjs 1). La proporción viene de la clave
`aspect` de los perfiles:

| Imagen | Proporción | Por qué |
| --- | --- | --- |
| Tarjeta `artwork` | **572:1016, fija** | fondo de la plantilla; la guía marca el logo y los textos |
| Tarjeta `thumbnail` | **1:1, fija** + punto focal | círculo del mapa; en el resto la app la recorta de 0.7:1 a 2.3:1 |
| Especie `thumbnail` | **1:1, fija** | listas y marcadores redondos del mapa |
| Especie `main`, `gallery` | libre | se usa el punto focal (abajo) |

- **Fija:** no se puede saltar; cancelar deja el campo como estaba. El servidor lo vuelve a
  comprobar con `dimensions:ratio` (`App\Concerns\ImageAspectRules`), solo al subir un archivo
  nuevo, así que editar sin cambiar la imagen sigue funcionando con las imágenes antiguas.
- **Tolerancia:** la de Laravel es de menos de un píxel en imágenes grandes (801 × 800 ya falla).
  Por eso `cropOutputSize()` (`lib/imageCrop.ts`) calcula el tamaño final redondeando una sola vez
  y ya dentro del perfil, y la compresión no vuelve a cambiarlo.
- **Original:** el recorte es un PNG; `cropOrigin()` recuerda la foto completa (archivo o URL
  guardada) para volver a recortar desde ella, y `cropSource()` el archivo elegido para guardar su
  peso en `original_bytes`. «Recortar de nuevo» abre el último recuadro aplicado (posición, giro y
  proporción), guardado en memoria con `rememberCrop()`.
- **Original para volver a recortar** (solo ilustración de tarjeta e imagen principal de especie):
  al guardar, `cropPayload()` envía una copia de la foto completa reducida a 2400 px (WebP q90,
  `CROP_SOURCE_PROFILE`, igual que `config('images.crop_source')`) como `artwork_source`/`main_source`
  y el recuadro en píxeles de esa copia como `artwork_crop`/`main_crop` (`x`, `y`, `width`,
  `height`, `rotate`, `scaleX`, `scaleY`, `aspectRatio`). `App\Services\ImageCropSource` guarda la
  copia en `<carpeta>/sources/` del disco público (`cards.artwork_source`/`artwork_crop`,
  `species_images.source_path`/`crop`). «Recortar imagen actual» abre esa copia con el último
  recuadro; sin copia, parte de la imagen guardada. Volver a recortar desde la copia envía solo el
  recuadro; una foto nueva usada sin recortar borra la copia. También se borra al eliminar la
  imagen (y la tarjeta), y la importación de paquetes la quita si reemplaza la ilustración: las
  copias **no viajan** en los paquetes. El recorte no lee la orientación EXIF (`checkOrientation:
  false`): el navegador ya la aplica, igual que `createImageBitmap` al hacer la copia.
- **Toda imagen con punto focal se puede recortar:** principal de especie y miniatura de tarjeta
  («Recortar imagen actual»), y cada foto de la galería, nueva o guardada. Recortar una foto
  guardada de la galería reemplaza su archivo al guardar (elemento con `id` y `file_index` en
  `gallery`): conserva id, orden y créditos, avisa que hay que revisarlos y se puede deshacer
  antes de guardar.
- **Sin comprobar:** la importación de paquetes y la conversión de Recursos → Optimización copian
  o recodifican sin mirar la proporción.

**Punto focal** (`species_images.focal_x`, `focal_y`, de 0 a 1 desde la esquina superior
izquierda; null = centro), solo en `main` y `gallery`. La app recorta esas fotos con `cover` en
marcos verticales y horizontales; el punto se aplica como `object-position` (en la app,
`contentPosition` de expo-image) y queda a la vista, no centrado. `FocalPointPicker.vue` muestra
las vistas previas con las medidas de la app (`APP_FRAMES` en `lib/focalPoint.ts`). Una imagen
principal nueva sin punto vuelve al centro; sin enviar `main_focal_x` se conserva el guardado.

La **miniatura de la tarjeta** tiene el suyo (`cards.thumbnail_focal_x`/`_y`, API
`thumbnail_focal`): se sube 1:1 por el círculo del mapa, pero el mazo (1.4:1), los diálogos del
duelo (~2.3:1) y la tarjeta sin imagen final (0.7:1) la recortan. `CardImageField` muestra el
selector con `CARD_THUMBNAIL_FRAMES`. Mismas reglas: miniatura nueva sin punto = centro, sin
enviar `thumbnail_focal_x` se conserva, y al eliminar la miniatura se borra. Viaja en los
paquetes como atributo de la tarjeta.
Contrato para la app: [focal-points-app.md](focal-points-app.md).

## Página "Recursos"

Administración → Recursos (`admin/assets`, permiso `assets.view`). La pestaña
**Optimización** muestra, por tipo (imágenes de tarjetas, imágenes de especies y modelos 3D de
tarjetas), cuántos archivos hay, cuánto pesan ahora y antes de optimizar, y cuántas imágenes
faltan por convertir; además los modelos 3D con sus medidas y los 20 archivos más pesados. Los
números salen de `App\Services\AssetInventory`. Sin peso original registrado cuenta el actual.
Con `assets.optimize` aparecen los dos paneles de conversión.

`assets.view` y `assets.optimize` solo los tiene `admin`; la migración
`add_assets_panel_permissions_and_link` se los da y agrega el enlace del menú sin volver a correr
los seeders.

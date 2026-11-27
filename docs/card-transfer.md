# Exportar e importar tarjetas y especies

Pestaña **Descargas** de Administración → Recursos (`admin/assets`, rutas
`admin.assets.transfers.*`). Mueve todas las tarjetas y especies de ZooDeck entre servidores, o
las respalda, en un solo `.zip`. Las clases conservan el nombre `CardTransfer*` / `CardPackage*`.

Requiere la extensión PHP `zip` (`ZipArchive`). Sin ella la pestaña muestra un aviso y
todas las acciones responden 503 (`CardTransferSupport::available()`).

Permisos: `assets.export` y `assets.import` (solo `admin`). Antes eran `cards.export` y
`cards.import`; la migración `rename_card_transfer_permissions_to_assets` pasó el permiso nuevo a
cada rol y usuario que tenía el viejo.

## Qué incluye

| Incluye | No incluye |
| --- | --- |
| Datos, diseño, estadísticas y mensajes de cada tarjeta | Ubicación de las especies, salvo que se pida |
| Preguntas y respuestas | Tarjetas obtenidas, intentos y bloqueos |
| QR: etiqueta, estado, vigencia y, si se pide, el token | Paquetes cifrados `packs/` (se regeneran solos) |
| Créditos y las licencias que usan | |
| Configuración por rareza (regla de puntos por `type`) y mensajes de duelo | |
| Imágenes, modelo 3D escalado y original | Modelos 3D de especies |
| Especies: datos, catálogos, categoría, etiquetas e imágenes (principal, miniatura, galería) con sus créditos | |

**Ubicación:** casilla "Incluir la ubicación de las especies" (`include_locations`, apagada por
omisión). El archivo dice dónde está exactamente cada animal: el panel lo advierte al marcarla.

## Formato (versión 3)

La versión 3 agrega `credits` a cada imagen de especie. Las versiones 1 (solo tarjetas, sin
`species/`; sus tarjetas solo se asignan a especies que ya existen) y 2 (especies sin créditos de
imágenes) se siguen leyendo.

```
manifest.json                format, version, exported_at, include_qr_tokens, counts, files{ruta: {sha256, bytes}}
licenses.json                licencias usadas, por slug
settings/rarity.json
settings/duel-messages.json
cards/<uuid>/card.json       uuid, species{slug, scientific_name, common_name}, attributes, files, questions, qr_codes, credits
species/<slug>/species.json  CardPackage::SPECIES_FIELDS, category{name, description}, tags[{name, slug}],
                             images{main, thumbnail, gallery[]} ({file, alt_text, focal_x, focal_y, original_bytes, is_active, credits}),
                             locations[{name, latitude, longitude, description, is_active, zone}] (solo con include_locations)
species/<slug>/main.<ext>, thumbnail.<ext>, gallery/NN.<ext>
cards/<uuid>/<campo>.<ext>   thumbnail_image, artwork_image, generated_image (webp/png/jpg), model_file, model_original_file (glb)
```

Nunca viajan id de la base. Las tarjetas se reconocen por `cards.uuid`, las licencias por
slug y las reglas de puntos por tipo. `CardPackage` define los nombres válidos.

## Flujo

1. **Exportar:** `ExportCardsJob` arma el zip en `storage/app/private/card-transfers/`.
2. **Importar:** se sube el zip, `AnalyzeCardImportJob` lo valida y prepara la vista
   previa (estado `awaiting_confirmation`). No escribe nada más.
3. La persona elige qué hacer con las tarjetas existentes, si se reemplaza la
   configuración global y a qué especie van las que no se encontraron.
4. `ImportCardsJob` importa (una importación a la vez).

El panel consulta `GET admin/cards/transfers` cada 2 s mientras algo está en curso.
Los paquetes se borran después de 7 días (`model:prune`, programado a diario), o al borrarlos
del historial.

Para paquetes que pesan más de lo que acepta una subida:

```bash
php artisan cards:export [ruta.zip] [--qr-tokens] [--locations]
php artisan cards:import ruta.zip [--overwrite] [--settings] [--dry-run]
```

## Reglas de la importación

- **Validación:** se rechaza el paquete entero si un nombre no coincide con
  `CardPackage::ENTRY_PATTERN` (así nunca se escribe fuera de las carpetas previstas), si
  los archivos no coinciden con el manifest, si un hash o tamaño no cuadra, si el tipo real
  de un archivo no es su extensión, o si pasa de `cards.transfer.max_uncompressed_bytes`.
  Una tarjeta con datos inválidos solo se omite.
- **Especies:** se importan antes que las tarjetas, cada una en su transacción. Se reconocen por
  `slug`; si no, por nombre científico (sin distinguir mayúsculas ni espacios) solo si coincide
  exactamente una. Las que no existen se crean; las existentes se omiten o se sobrescriben con la
  misma opción que las tarjetas (al sobrescribir se conserva el slug local, se reemplazan datos,
  etiquetas e imágenes con sus créditos, y la ubicación solo si el paquete la trae). Los créditos
  de imagen usan los mismos campos que los de tarjeta (`CardPackage::CREDIT_FIELDS` y
  `license_slug`), siempre con `asset: "image"` y sin `based_on_asset`.
- **Tarjetas:** van a la especie del paquete. Solo si no está en él (paquete versión 1) ni en este
  servidor, la persona elige una especie o las omite.
- **Lo que falta en este servidor** (por nombre, sin distinguir mayúsculas), lo decide la persona
  en la vista previa: cada **categoría** se crea o se usa una existente (la especie la necesita);
  cada **etiqueta** se crea, se usa una existente o se omite; cada **zona** se cambia por una
  existente o su ubicación se omite (una zona necesita su polígono, no se crea desde el paquete).
  En `cards:import` las categorías y etiquetas se crean y las zonas desconocidas se omiten.
- **Existentes:** se omiten o se sobrescriben (lo elige la persona). Sobrescribir reemplaza
  datos, archivos y créditos.
- **Preguntas:** se reconocen por `QuizQuestion::normalizeText`. Nunca se borran, porque
  `quiz_attempts.question_ids` las usa. Las que no vienen se desactivan, y las respuestas se
  reemplazan solo si cambiaron.
- **QR:** con tokens, el QR impreso sigue sirviendo y, si otra tarjeta ya usa el token, se
  crea con uno nuevo. Sin tokens, solo las tarjetas nuevas reciben QR (con tokens nuevos).
- **Licencias:** por slug. Una que no existe se crea desactivada (`is_selectable = false`).
- **Punto focal:** `focal_x`/`focal_y` (0 a 1) de cada imagen son opcionales dentro de la
  versión 3: los paquetes anteriores se importan con null (centro).
- **Proporción:** la importación no comprueba la proporción fija de miniaturas e ilustraciones
  (ver "Recorte y punto focal" en [image-optimization.md](image-optimization.md)).
- **Original para volver a recortar:** las copias `artwork_source` y la de la imagen principal
  de especie no viajan. Si la importación reemplaza la ilustración de una tarjeta, se borra su
  copia; las imágenes de especie importadas llegan sin ella.
- **Archivos:** se copian tal cual (ya vienen optimizados y escalados). Cada tarjeta va en
  su propia transacción: si falla, se borran sus archivos nuevos y las demás siguen.

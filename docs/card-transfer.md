# Exportar e importar tarjetas

Pestaña **Importar / Exportar** del índice de tarjetas (`admin/cards`). Mueve todo el
catálogo de ZooDeck entre servidores, o lo respalda, en un solo `.zip`.

Requiere la extensión PHP `zip` (`ZipArchive`). Sin ella la pestaña muestra un aviso y
todas las acciones responden 503 (`CardTransferSupport::available()`).

Permisos: `cards.export` y `cards.import` (solo `admin`; la migración
`add_card_transfer_permissions` se los da sin volver a correr el seeder).

## Qué incluye

| Incluye | No incluye |
| --- | --- |
| Datos, diseño, estadísticas y mensajes de cada tarjeta | Especies (solo se referencian) |
| Preguntas y respuestas | Tarjetas obtenidas, intentos y bloqueos |
| QR: etiqueta, estado, vigencia y, si se pide, el token | Paquetes cifrados `packs/` (se regeneran solos) |
| Créditos y las licencias que usan | |
| Configuración por rareza (regla de puntos por `type`) y mensajes de duelo | |
| Imágenes, modelo 3D escalado y original | |

## Formato (versión 1)

```
manifest.json                format, version, exported_at, include_qr_tokens, counts, files{ruta: {sha256, bytes}}
licenses.json                licencias usadas, por slug
settings/rarity.json
settings/duel-messages.json
cards/<uuid>/card.json       uuid, species{slug, scientific_name, common_name}, attributes, files, questions, qr_codes, credits
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
php artisan cards:export [ruta.zip] [--qr-tokens]
php artisan cards:import ruta.zip [--overwrite] [--settings] [--dry-run]
```

## Reglas de la importación

- **Validación:** se rechaza el paquete entero si un nombre no coincide con
  `CardPackage::ENTRY_PATTERN` (así nunca se escribe fuera de las carpetas previstas), si
  los archivos no coinciden con el manifest, si un hash o tamaño no cuadra, si el tipo real
  de un archivo no es su extensión, o si pasa de `cards.transfer.max_uncompressed_bytes`.
  Una tarjeta con datos inválidos solo se omite.
- **Especies:** por `slug`; si no, por nombre científico (sin distinguir mayúsculas ni
  espacios) solo si coincide exactamente una. Si no, la persona elige una o la omite.
  Nunca se crean especies.
- **Existentes:** se omiten o se sobrescriben (lo elige la persona). Sobrescribir reemplaza
  datos, archivos y créditos.
- **Preguntas:** se reconocen por `QuizQuestion::normalizeText`. Nunca se borran, porque
  `quiz_attempts.question_ids` las usa. Las que no vienen se desactivan, y las respuestas se
  reemplazan solo si cambiaron.
- **QR:** con tokens, el QR impreso sigue sirviendo y, si otra tarjeta ya usa el token, se
  crea con uno nuevo. Sin tokens, solo las tarjetas nuevas reciben QR (con tokens nuevos).
- **Licencias:** por slug. Una que no existe se crea desactivada (`is_selectable = false`).
- **Archivos:** se copian tal cual (ya vienen optimizados y escalados). Cada tarjeta va en
  su propia transacción: si falla, se borran sus archivos nuevos y las demás siguen.

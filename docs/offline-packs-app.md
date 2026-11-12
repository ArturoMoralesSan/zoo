# Paquetes sin conexión de ZooDeck: guía para la app móvil

> **Estado:** el servidor ya está implementado (2026-11-12); esta parte, la de la app,
> todavía no.
> Recomendaciones e instrucciones para implementar la parte del cliente en
> `../zooApp` (rama `feat/zoodeck`, Expo SDK 57 + ViroReact). El **contrato de la
> API** y el comportamiento del servidor están en
> [offline-packs-backend.md](offline-packs-backend.md); léelo primero.
>
> Para quien implemente esto en `zooApp`: sigue el `AGENTS.md` de ese repositorio
> (estilo, NativeWind, `ui/`, SWR solo en `src/hooks/**`, sin atribución a IA en los
> commits). Ahí manda ese archivo sobre este.

## Objetivo

Que un visitante descargue **en casa, con Wi-Fi**, los archivos cifrados de todas las
tarjetas, y que en el zoológico, con datos móviles:

- escanear un QR coloque el modelo en AR **sin descargar el GLB**;
- ganar un duelo muestre la tarjeta **sin descargar la imagen**;
- la colección se vea **sin conexión**, incluso después de cerrar la app.

Las tarjetas sin capturar siguen ocultas: sus archivos están cifrados y la llave
llega solo cuando la API ya revelaría el archivo (al escanear el QR o al ganar).

El paquete es **opcional**: si un archivo no está en el teléfono, la app usa la URL
de siempre.

## Cada archivo se descarga y se gestiona por separado

No hay zip ni bundle. El catálogo (`GET /api/cards/pack`) lista **una entrada por
archivo**: hasta tres por tarjeta (`model`, `card_image`, `thumbnail`). Cada entrada
tiene su `id` (su versión), su `url`, `bytes` y `sha256`, y su llave llega aparte en
`pack`. La app descarga, verifica, descifra, actualiza y borra **cada archivo por su
cuenta**:

- Si un admin cambia la escala de un modelo, solo se vuelve a bajar ese modelo.
- Si una descarga se corta, solo se reanuda ese archivo.
- "Descargar todo" pone en cola las entradas que faltan, una por una.

## Lo que hay hoy en `zooApp`

| Pieza | Archivo | Qué importa aquí |
| --- | --- | --- |
| Lista de tarjetas | `src/services/cards.ts` | El `ETag` y la lista se guardan **solo en memoria**: al abrir la app sin conexión, la colección sale vacía. |
| Tipos | `src/types/cards.ts` | `OwnedCard`, `DuelCard`, `DuelResult`. Falta `pack`. |
| Miniatura | `src/components/deck/CollectionTile.tsx` | `Image` con `card.thumbnail_image`. |
| Imagen final | `src/components/deck/CardCarousel.tsx` | `card.card_image_url ?? card.thumbnail_image`. |
| Vista 3D | `src/app/deck/[id].tsx`, `src/components/deck/CardViewerScene.tsx` | `model_url` en `Viro3DObject` (`key` = URL). |
| AR de la colección | `src/app/deck/ar/[id].tsx`, `src/components/ar/ModelPlacementScene.tsx` | `model_url`. |
| Duelo | `src/app/duel/scan.tsx`, `src/app/duel/play.tsx` | Miniatura y `model_url` del inicio; `card_image_url` al ganar. |
| Red | `src/hooks/useNetworkStatus.ts`, `src/services/swr-config.tsx` | NetInfo y AppState ya disparan la revalidación. `state.details.isConnectionExpensive` indica datos móviles. |
| Archivos | `expo-file-system` (ya instalado) | `File`, `Directory`, `Paths`, `File.bytes()`, `write(Uint8Array)`, `move()`, `delete()`, `DownloadTask` (progreso y reanudar), `Paths.availableDiskSpace`. |
| Clave-valor | `expo-secure-store` | Lo único que hay; sirve para el ajuste de datos móviles, no para índices grandes. |
| Sesión | `src/contexts/AuthContext.tsx` | `logout()` y `user.id`. |
| Pantalla ZooDeck | `src/app/(tabs)/zoodeek.tsx` | Vista previa del mapa, "Mi colección" y la lista de acciones. |
| Panel | `src/app/(tabs)/panel.tsx` | **"Configuración" es solo un aviso de "próximamente".** |

**Falta una dependencia:** no hay librería de cifrado. Se recomienda `expo-crypto`,
que en SDK 57 trae AES-GCM (`aesDecryptAsync`, `AESEncryptionKey.import`,
`AESSealedData.fromCombined`) y SHA-256 (`digest`). **Pide permiso antes de
instalarla** (`npx expo install expo-crypto`): necesita `npx expo run:android`.

## Almacenamiento en el teléfono

Todo va en `Paths.document`, no en la caché, porque el sistema puede vaciar la caché
justo antes de la visita.

```
Paths.document/
  packs/
    index.json                       catálogo local: ETag + entradas descargadas
    {id}.bin                         archivos cifrados, compartidos por el dispositivo
  cards/{userId}/
    index.json                       (card_id, kind) → { id, path, bytes }
    cards.json                       última respuesta de /api/cards y su ETag
    {card_id}-{kind}-{id}.glb|.webp  archivos ya descifrados
```

- **`packs/`** es igual para cualquier usuario del dispositivo: está cifrado.
- **`cards/{userId}/`** es del usuario: se borra en `logout()`.
- `index.json` se escribe a un archivo temporal y luego se mueve (`move()`), para no
  dejarlo a medias si la app se cierra.
- Ajuste "Usar datos móviles para actualizar": SecureStore, desactivado por defecto.

## Qué archivo usa la app (`useCardAsset`)

Un solo hook decide de dónde sale cada archivo. Todas las pantallas de la tabla de
arriba lo usan en lugar de leer `*_url` directamente.

```ts
useCardAsset(cardId, kind, ref /* pack.<kind> | undefined */, url /* *_url */)
// → { uri: string | null, source: 'local' | 'pack' | 'stale' | 'remote' | 'none', loading }
```

Orden:

1. **Descifrado y actual:** existe `{card_id}-{kind}-{ref.id}` → `file://…`.
2. **En el paquete:** existe `packs/{ref.id}.bin` y hay `ref.key` → se descifra
   **una vez**, se guarda en `cards/{userId}/` y se usa.
3. **Copia vieja:** hay un archivo de la misma `(card_id, kind)` con otro `id`, y la
   app está sin conexión o con datos móviles y el ajuste apagado → se usa la copia
   vieja y el archivo se marca como **pendiente**.
4. **URL:** se usa `url` tal cual. En segundo plano se descarga al almacén, para no
   volver a pedirlo.
5. **Nada:** `null` y el estado vacío de cada pantalla.

`Viro3DObject` e `Image` aceptan `file://`. Mantén `key={uri}` en `AnimalModel`
(`CardViewerScene.tsx`) para que un modelo nuevo se vuelva a medir.

Descifrar:

```ts
const sealed = AESSealedData.fromCombined(await new File(binUri).bytes())
const key = await AESEncryptionKey.import(base64ToBytes(ref.key))
const plain = await aesDecryptAsync(sealed, key, { output: 'bytes' })
// write a temp file, then move() into place
```

Si el descifrado falla (la etiqueta GCM no coincide), el `.bin` está mal: bórralo y
sigue con el paso 4.

## Sincronización (`usePackSync`)

Se monta **una vez**, dentro de `AuthProvider` en `src/app/_layout.tsx`, y solo
trabaja con un usuario con sesión iniciada.

**Cuándo corre:**
- al abrir la app,
- al volver al primer plano (AppState),
- al recuperar la conexión (NetInfo),
- después de una captura (desde `addOwnedCard` en `useDataRefresh`),
- al tocar "Descargar" o "Actualizar ahora".

**Qué hace:**

1. Pide `GET /api/cards/pack` con el `ETag` guardado. Con un `304`, termina.
2. Compara el catálogo con `packs/index.json`, **entrada por entrada**:
   - un `id` que no está en el teléfono es una descarga **pendiente**;
   - un `.bin` local que ya no está en el catálogo es un **huérfano** y se borra;
   - una copia descifrada cuya `(card_id, kind)` ya no está en el catálogo, o cuyo
     `pack.<kind>` llega como `null` en `/api/cards`, también se borra;
   - una `(card_id, kind)` que cambió de `id` es una **actualización**.
3. Decide si puede descargar ahora:
   - con Wi-Fi, sí;
   - con datos móviles, solo con el ajuste activado o si el usuario tocó el botón
     (con aviso del tamaño);
   - sin conexión, no.
4. Antes de empezar, compara la suma de `bytes` con `Paths.availableDiskSpace`, con
   margen. Si no cabe, el estado pasa a "Sin espacio".
5. Descarga **un archivo a la vez** con `DownloadTask` (progreso y reanudar), primero
   en `{id}.bin.part`. Después verifica `bytes` y `sha256`, renombra el archivo y lo
   anota en `packs/index.json`.
   Si el servidor responde `200` en vez de `206` a una reanudación (pasa con
   `php artisan serve` en desarrollo), descarta el `.part` y empieza de cero.
   No lo trates como error.
6. En las tarjetas obtenidas descifra enseguida el archivo nuevo, actualiza
   `cards/{userId}/index.json` y **después** borra la versión anterior.
7. Un `404` o un hash incorrecto vuelve a pedir el catálogo y reintenta una vez. Nunca
   bloquea la interfaz ni muestra errores modales.

El estado (`idle`, `checking`, `downloading {done, total, bytes}`, `ready`,
`pending {count, bytes}`, `noSpace`, `error`) se publica en un store pequeño que lee
`usePackStatus()`.

## Qué pasa cuando cambia un archivo

Los cambios posibles en el panel son una escala nueva del modelo (GLB nuevo), un
diseño nuevo de la tarjeta (`generated_image`) o una miniatura nueva. En todos los
casos el archivo tiene **otro `id`**, y solo ese archivo se ve afectado.

| Situación | Qué hace la app |
| --- | --- |
| En Wi-Fi | La siguiente sincronización baja solo ese `.bin`, lo descifra si la tarjeta es suya y reemplaza la copia. El usuario no ve nada. |
| Con datos móviles, ajuste apagado | **Sigue mostrando la versión anterior.** La tarjeta de ZooDeck dice "1 actualización · 0.8 MB". Se baja al volver al Wi-Fi. |
| Con datos móviles, ajuste encendido | Se baja de una vez, como en Wi-Fi. |
| Sin conexión | Versión anterior; se revisa al reconectar. |
| Escanea el QR de una tarjeta cuyo modelo cambió y no lo ha bajado | El `.bin` viejo no se puede abrir: su llave nunca se envía. Se baja **solo ese GLB** por URL (≈1 MB) con un `Notice` ("Descargando el animal…"). Es raro si la app sincroniza en Wi-Fi. |
| Escanea el QR de una tarjeta cuya **miniatura** cambió y no la ha bajado | Igual que el modelo, pero sin aviso: la miniatura pesa unos 50 KB y se baja por URL. |
| Gana una tarjeta cuyo **diseño** cambió y no lo ha bajado | La llave que llega al ganar es del `id` nuevo. Se baja **solo la imagen final** por URL (hasta unos 650 KB); el modelo y la miniatura salen del paquete. |
| Una tarjeta suya tiene diseño o miniatura nuevos | Igual que con el modelo: se baja ese único archivo, se descifra con la llave que ya trae `/api/cards` y se reemplaza la copia. Con datos móviles sigue la versión anterior. |
| Se eliminó la miniatura, la imagen final o el modelo | `pack.<kind>` llega como `null` y la entrada sale del catálogo: se borran el `.bin` **y** la copia descifrada. La pantalla usa su respaldo: el carrusel muestra la miniatura si falta la imagen final, y la cuadrícula muestra el estado vacío. |
| El archivo nuevo no tiene copia cifrada (falló el cifrado o no se ha corrido `cards:packs:build`) | `pack.id` ya es el nuevo, pero el catálogo no lo trae: se usa la URL o la copia vieja. |
| La tarjeta se desactivó o se revocó la captura (modo de desarrollo) | Sale del catálogo o de `/cards`: se borran su `.bin` y su copia descifrada. |
| Cambió la fuente, un color, el nombre o las estadísticas | Todo eso va dibujado en la imagen final, y el editor la vuelve a generar al guardar: es un `card_image` con otro `id`, y se baja solo esa imagen. El nombre y las estadísticas de las listas (`DeckCardTile`, `CollectionTile`) llegan además en el JSON de `/api/cards`. La app nunca dibuja la tarjeta por su cuenta; el reverso (`assets/models/card/Back.glb`) viene incluido en la app. |

**Cómo se entera la app:** (1) el `ETag` de `/api/cards/pack` en cada sincronización;
(2) los `pack.*.id` que trae `/api/cards`; (3) los `pack.*.id` del inicio del duelo.
Un `id` distinto del local significa que ese archivo cambió. No hace falta push.

## Cómo se ve

**Recomendación: en la pantalla de ZooDeck, no en Configuración.** Configuración
todavía no existe (es un aviso de "próximamente"), y el paquete es parte del juego: el
visitante lo prepara para la visita.

1. **Tarjeta "Paquete sin conexión"** en `src/app/(tabs)/zoodeek.tsx`, entre la vista
   previa del mapa y "Mi colección". Es un `ListCard` con un icono de descarga y una
   línea de estado:

   | Estado | Texto | Tono |
   | --- | --- | --- |
   | Nada descargado | "Descarga tus tarjetas en Wi-Fi · 42 MB" | destacado |
   | Descargando | "Descargando… 60 %" | normal |
   | Todo al día | "Listo para tu visita" | normal, con icono de check |
   | Cambios pendientes | "3 actualizaciones · 2.1 MB" | destacado |
   | Sin espacio | "No hay espacio suficiente" | aviso |

   Al tocarla se abre la pantalla de detalle. Si todo está al día, puede verse más
   discreta.

2. **Aviso único**: un `Notice` en ZooDeck la primera vez que el usuario está con
   datos móviles y no ha descargado nada: "Descarga el paquete en casa para ahorrar
   datos en tu visita". Se recuerda con `markHintShown` (`src/services/hints.ts`).

3. **Pantalla `src/app/deck/offline.tsx`** (nueva, sin diseño en el PDF; usa
   `ScreenBackground`, `TopBar` y componentes de `ui/`):
   - tamaño total, lo descargado y la barra de progreso;
   - botón "Descargar" o "Actualizar ahora", con el tamaño. Con datos móviles pide
     confirmación en un `Modal`;
   - interruptor "Usar datos móviles para actualizar" (apagado por defecto);
   - "Liberar espacio": borra `packs/` y vuelve a las URL;
   - "Última revisión: hace 5 min".

4. **Después**, cuando exista Configuración en el Panel, que tenga un acceso a esta
   misma pantalla. No dupliques la lógica.

Pide al usuario el diseño final (código A-xx) antes de pulir la interfaz. Los
estados y textos de arriba son lo mínimo.

## Implementación

**Antes de empezar:** el servidor debe tener el endpoint y el campo `pack` (ver el
documento del servidor). La app debe funcionar igual si `pack` no viene, porque
entonces solo usa las URL.

1. **Tipos** (`src/types/pack.ts`): `PackKind`, `PackFile`, `PackManifest`,
   `PackManifestResponse` y `PackRef = { id: string; key: string }`. En
   `src/types/cards.ts`: `pack?: Partial<Record<PackKind, PackRef | null>>` en
   `OwnedCard` y en `DuelCard`.
2. **Dependencia:** **pregunta antes** de instalar `expo-crypto` y de hacer
   `npx expo run:android`.
3. **`src/services/packStore.ts`:** rutas, lectura y escritura atómica de los
   `index.json`, `wipeUser(userId)`.
4. **`src/services/packCrypto.ts`:** `decrypt(binUri, key) → Uint8Array` y
   `sha256(fileUri)`.
5. **`src/services/pack.ts`:** `getPackManifest()` con el `ETag` persistido, como en
   `cards.ts`.
6. **Persistir `/api/cards`:** en `src/services/cards.ts`, guardar
   `{ etag, cards }` en `cards/{userId}/cards.json` y leerlo al arrancar, para que la
   colección funcione sin conexión después de reiniciar.
7. **`src/hooks/usePackSync.ts`** y **`src/hooks/usePackStatus.ts`**, montados en
   `src/app/_layout.tsx` dentro de `AuthProvider`.
8. **`src/hooks/useCardAsset.ts`**, y cambiar todas las pantallas de la tabla de
   arriba para usarlo. Los modos de depuración (`utils/zoodeckDebug.ts`) siguen con
   sus archivos incluidos en la app.
9. **Al cerrar sesión:** `AuthContext.logout()` llama a `wipeUser(user.id)`.
10. **Interfaz:** la tarjeta en ZooDeck, el `Notice` y `src/app/deck/offline.tsx`.
11. **Revisión final:** `npm run lint` y `npx tsc --noEmit` en 0.

## Cómo probarlo en el teléfono

**Servidor de desarrollo (repositorio `zoo`).** El servidor ya está listo:
`php artisan cards:packs:build` se corrió el 2026-11-12 y creó 12 archivos para las 4
tarjetas activas (unos 4.3 MB en total). Antes de probar:

1. El API tiene que responder en la dirección de `EXPO_PUBLIC_API_URL` de la app (una
   IP de la red local, puerto 8000), por ejemplo con
   `php artisan serve --host=0.0.0.0 --port=8000`.
2. Comprueba el catálogo con el token de la app:
   `curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://<ip>:8000/api/cards/pack`.
3. Si se suben tarjetas nuevas por SQL o se copian archivos a mano, vuelve a correr
   `php artisan cards:packs:build`. Lo que se cambie en el panel se cifra solo.

Usa la receta de pruebas sin conexión con ADB del `AGENTS.md` de `zooApp` (solo por
USB, y restaura siempre la red).

1. En Wi-Fi, descargar todo y comprobar que `packs/` tiene 3 archivos por tarjeta.
2. Desactivar la red. Reiniciar la app: la colección, la vista 3D y el AR de una
   tarjeta obtenida deben funcionar.
3. Activar solo datos móviles. Escanear el QR de una tarjeta nueva: el modelo debe
   aparecer sin descargar el GLB (revisa los logs de Metro o del servidor).
4. Ganar el duelo: la imagen final aparece sin descargarse.
5. Con datos móviles, cambiar la escala del modelo de una tarjeta obtenida en el
   panel. Reabrir la app: debe seguir mostrando el modelo anterior y decir
   "1 actualización". Al volver al Wi-Fi, se baja **solo** ese archivo.
   Repetir con una miniatura nueva y con un diseño nuevo de la tarjeta: cada uno
   cuenta como una actualización y se baja solo.
6. Quitar la miniatura de una tarjeta obtenida en el panel: tras sincronizar, la
   copia local desaparece y la cuadrícula muestra el estado vacío.
7. Cortar la red a mitad de una descarga: al volver, se reanuda ese archivo y no
   empieza de cero.
8. Cerrar sesión: `cards/{userId}/` desaparece y `packs/` se queda.

## Fuera de alcance

- Duelos sin conexión: tendrán su propio documento.
- Especies, eventos y mapa sin conexión.
- Pantalla de Configuración en el Panel (solo se deja preparado el acceso).

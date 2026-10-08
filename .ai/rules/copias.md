---
paths:
  - app/Domain/Copia/**
  - config/copias.php
  - tests/Feature/Copias/**
---

# Copias de seguridad

§ 6: «backups cifrados con restauración probada y periodicidad documentada».
La herramienta entra en el alcance de su propio SGSI (invariante 8), así que
esto no es infraestructura que se monta después: es un requisito del producto.

## Qué hay

- **`copias:hacer`**, cada noche a las 02:00. Vuelca la base con `pg_dump`, cifra
  el volcado, lo sube con su manifiesto al disco `copias`, copia de forma
  incremental los objetos de `evidencias`, `documentos` y `adjuntos`, y retira
  los volcados que pasan de `COPIAS_CONSERVAR_DIAS`. El último no se retira
  nunca.
- **`copias:verificar`**, los domingos a las 04:00. Descarga la última copia,
  comprueba su huella, la descifra, la restaura en `statera_verificacion` y la
  compara: cada tabla con las filas que tenía al volcarse y cada fichero que la
  base nombra con su SHA-256. Deja el informe en `verificaciones/` y **sale con
  error** si algo no cuadra.

## Decisiones que no se ven leyendo el código

- **Los recuentos y el volcado salen de la misma instantánea.** Se abre una
  transacción `REPEATABLE READ`, se exporta con `pg_export_snapshot()`, se cuenta
  dentro y se le pasa a `pg_dump --snapshot`. Contar antes o después dejaría
  colar las escrituras de entre medias, y la verificación daría por mala una copia
  buena cada vez que alguien trabajara de madrugada.
- **Un rol propio, `statera_copias`, con BYPASSRLS y sin escritura**
  (`pg_read_all_data`). Con RLS, `pg_dump` se niega a volcar una tabla que
  filtraría, y un rol de aplicación no ve más que su organización. Es el único rol
  que se salta la tercera capa, y por eso no puede escribir nada. Es también dueño
  de `statera_verificacion`, que vacía entera en cada comprobación.
- **Cifrado con libsodium** (XChaCha20-Poly1305 en modo *secretstream*), que viene
  con PHP: ninguna dependencia nueva. Cada trozo va autenticado y el último lleva
  marca de final, así que una copia **truncada** no descifra y se nota.
- **`COPIAS_CLAVE` no es `APP_KEY`.** La clave de la aplicación se rota por motivos
  que no tienen que ver con esto, y rotarla dejaría ilegibles las copias
  anteriores. Esta clave se guarda también fuera del servidor: sin ella no se
  restaura nada.
- **Primero la base, después los objetos.** Lo que se sube entre medias queda en el
  espejo aunque la base no lo nombre, y eso no molesta. Al revés, una evidencia
  subida entre medias estaría en la base y no en la copia.
- **El espejo nunca borra.** Un objeto que desaparece del origen es justo lo que
  una copia tiene que poder devolver.
- **De las versiones de documento sólo se comprueban las emitidas.** El borrador se
  regenera y se borra, y lo que hay que poder devolver es lo que se entregó.

## Lo que no hace, y queda declarado

- **El espejo de objetos no lo cifra la aplicación**, sino el cifrado en reposo
  del almacén (SSE, punto 35), como a los otros tres discos. El volcado de la
  base sí va cifrado por la aplicación, porque contiene todo.
- **Los ficheros sin fila no se contrastan con ninguna huella**: el logo de la
  organización y las fotos de perfil se copian igual, pero la base no guarda su
  SHA-256.
- **La verificación no se registra como evidencia dentro de Statera.** Deja su
  informe en el bucket de copias. Enlazarlo como evidencia de A.8.13 y `mp.info.6`
  pide saber cuál es la organización dueña de la instalación, y eso no está
  modelado.
- **En producción el bucket de copias tiene que vivir en otra cuenta o región y con
  Object Lock.** En desarrollo es un bucket más de MinIO.
- **Los tests de punta a punta copian la base recién migrada.** El rol de copias va
  por otra conexión y no ve la transacción abierta del test. La comprobación de
  ficheros con filas reales se hizo a mano sobre la base de desarrollo.

## Quién lee el estado de las copias (punto 55)

**`EstadoDeLasCopias`** resume la última copia (por el nombre del directorio,
`Y-m-d\THis`) y el último informe de `verificaciones/` para la salud del
servicio de la plataforma. **No hay tabla**: los informes que dejan los dos
comandos son la fuente. **Si el disco no responde, lo dice en vez de lanzar
una excepción**, porque una pantalla de salud que da un 500 cuando cae el
almacenamiento es justo la que no sirve.

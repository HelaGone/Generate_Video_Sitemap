# Changelog
Cambios registrados en el plugin Generate Video Sitemap desde Jiluo 2020

## [Released]
- 2020-07-09

### Changed
- Se cambió el modo de generar el sitemap. Se genera en la acción `post_publish` y guarda el archivo video_sitemap en root

### Fixed
- Se arregló el elemento player_loc en el archivo.
- Se arregló la función `gvsm_ISO8601ToSeconds` y `gvsm_get_youtube_duration` para capturar errores y evitar que el programa se rompa

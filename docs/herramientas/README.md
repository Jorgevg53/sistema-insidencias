# Regenerar el documento Word

`generar_docx.js` arma `docs/Documentacion_Sistema_Incidencias_TESCHI.docx` a partir de los Markdown de `docs/`
(las imágenes de los diagramas Mermaid están en `docs/img/18_diagrama_er.png` y `19_flujo_estados.png`).

Requiere [Node.js](https://nodejs.org/):

```bash
cd docs/herramientas
npm install docx
node generar_docx.js
```

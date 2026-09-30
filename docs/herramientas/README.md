# Regenerar los documentos Word

`generar_docx.js` arma tres documentos en `docs/` a partir de los Markdown:

| Comando | Resultado |
|---|---|
| `node generar_docx.js usuario` | `Manual_de_Usuario_Sistema_Incidencias_TESCHI.docx` |
| `node generar_docx.js tecnico` | `Manual_Tecnico_Sistema_Incidencias_TESCHI.docx` |
| `node generar_docx.js completo` | `Documentacion_Sistema_Incidencias_TESCHI.docx` |
| `node generar_docx.js` | Los tres |

Las imágenes están en `docs/img/` (capturas de la interfaz, `18_diagrama_er.png`, `19_flujo_estados.png` y
`23_arquitectura.png`). Los diagramas se dibujaron con Mermaid a partir del código de los Markdown.

Requiere [Node.js](https://nodejs.org/):

```bash
cd docs/herramientas
npm install docx
node generar_docx.js
```

Al abrir un Word, si el índice aparece vacío: clic derecho sobre él → **Actualizar campos**.

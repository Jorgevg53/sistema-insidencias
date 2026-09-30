/*
 * Genera los documentos Word a partir de los Markdown de docs/ (misma fuente
 * que la documentación del repositorio).
 *
 *   node generar_docx.js             -> genera los tres documentos
 *   node generar_docx.js usuario     -> solo el manual de usuario
 *   node generar_docx.js tecnico     -> solo el manual técnico
 *   node generar_docx.js completo    -> solo la documentación completa
 */
const fs = require("fs");
const path = require("path");
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, ImageRun,
  Table, TableRow, TableCell, WidthType, ShadingType, BorderStyle, LevelFormat,
  TableOfContents, Header, Footer, PageNumber, PageBreak, ExternalHyperlink,
} = require("docx");

const DOCS = path.join(__dirname, "..");
const DOCUMENTOS = {
  completo: {
    salida: "Documentacion_Sistema_Incidencias_TESCHI.docx",
    archivos: ["05_despliegue.md", "01_instalacion.md", "02_manual_usuario.md", "06_manual_tecnico.md", "03_base_de_datos.md", "04_arquitectura_y_seguridad.md"],
    titulo: "Documentación completa",
    contenido: "Contenido: despliegue · instalación · manual de usuario · manual técnico · base de datos · arquitectura y seguridad",
    descripcion: "Guía de despliegue, instalación, manuales de usuario y técnico, base de datos y arquitectura",
  },
  usuario: {
    salida: "Manual_de_Usuario_Sistema_Incidencias_TESCHI.docx",
    archivos: ["02_manual_usuario.md"],
    titulo: "Manual de usuario",
    contenido: "Para estudiantes, docentes, personal administrativo, coordinadores y administradores",
    descripcion: "Manual de usuario del Sistema de Gestión de Incidencias",
  },
  tecnico: {
    salida: "Manual_Tecnico_Sistema_Incidencias_TESCHI.docx",
    archivos: ["06_manual_tecnico.md", "03_base_de_datos.md", "04_arquitectura_y_seguridad.md", "01_instalacion.md", "05_despliegue.md"],
    titulo: "Manual técnico",
    contenido: "Contenido: manual técnico · base de datos MongoDB · arquitectura y seguridad · instalación · despliegue",
    descripcion: "Manual técnico, base de datos, arquitectura, instalación y despliegue",
  },
};
let CFG = DOCUMENTOS.completo;
const MERMAID = { erDiagram: "img/18_diagrama_er.png", stateDiagram: "img/19_flujo_estados.png" };

const COLOR = "007A3D";
const ANCHO_CONTENIDO = 9360; // DXA: carta con márgenes de 1"
const MAX_ANCHO_PX = 620;
const MAX_ALTO_PX = 800;

let instanciaLista = 0;
let ultimaFueLista = false;

/* ---------- Texto con formato en línea ---------- */
function runsEnLinea(texto, base = {}) {
  const runs = [];
  // **negrita**, *cursiva*, `código`, [texto](url)
  const re = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
  let ultimo = 0, m;
  while ((m = re.exec(texto)) !== null) {
    if (m.index > ultimo) runs.push(new TextRun({ text: texto.slice(ultimo, m.index), ...base }));
    const t = m[0];
    if (t.startsWith("**")) runs.push(...runsEnLinea(t.slice(2, -2), { ...base, bold: true }));
    else if (t.startsWith("`")) runs.push(new TextRun({ text: t.slice(1, -1), font: "Consolas", size: 19, color: "005A2D", ...base }));
    else if (t.startsWith("[")) {
      const [, txt, url] = t.match(/\[([^\]]+)\]\(([^)]+)\)/);
      if (/^https?:/.test(url)) {
        runs.push(new ExternalHyperlink({ link: url, children: [new TextRun({ text: txt, style: "Hyperlink", ...base })] }));
      } else {
        runs.push(new TextRun({ text: txt.replace(/`/g, ""), ...base }));
      }
    } else runs.push(new TextRun({ text: t.slice(1, -1), italics: true, ...base }));
    ultimo = m.index + t.length;
  }
  if (ultimo < texto.length) runs.push(new TextRun({ text: texto.slice(ultimo), ...base }));
  // Enlaces automáticos <https://...>
  return runs.flatMap((r) => r);
}

function limpiarAutolinks(t) {
  return t.replace(/<(https?:\/\/[^>]+)>/g, "[$1]($1)");
}

/* ---------- Imágenes ---------- */
function dimensionesPng(buf) {
  return { ancho: buf.readUInt32BE(16), alto: buf.readUInt32BE(20) };
}

function imagen(rutaRelativa, pie) {
  const buf = fs.readFileSync(path.join(DOCS, rutaRelativa));
  let { ancho, alto } = dimensionesPng(buf);
  const escala = Math.min(MAX_ANCHO_PX / ancho, MAX_ALTO_PX / alto, 1);
  const w = Math.round(ancho * escala), h = Math.round(alto * escala);
  const partes = [
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 120, after: 60 },
      children: [new ImageRun({
        type: "png", data: buf, transformation: { width: w, height: h },
        altText: { title: pie || "Imagen", description: pie || "Imagen", name: path.basename(rutaRelativa) },
      })],
    }),
  ];
  if (pie) {
    partes.push(new Paragraph({
      alignment: AlignmentType.CENTER, spacing: { after: 200 },
      children: [new TextRun({ text: pie, italics: true, size: 18, color: "6B7280" })],
    }));
  }
  return partes;
}

/* ---------- Tablas ---------- */
function celdas(linea) {
  // \| (barra escapada) dentro de una celda no separa columnas.
  return linea.trim().replace(/^\||\|$/g, "").replace(/\\\|/g, "\u0001").split("|")
    .map((c) => c.replace(/\u0001/g, "|").trim());
}

function tabla(lineas) {
  const encabezado = celdas(lineas[0]);
  const filas = lineas.slice(2).map(celdas);
  const n = encabezado.length;

  // Ancho proporcional al contenido (con mínimo), sumando el ancho total.
  const largo = encabezado.map((h, i) =>
    Math.max(h.length, ...filas.map((f) => Math.min((f[i] || "").length, 34)), 6));
  const total = largo.reduce((a, b) => a + b, 0);
  let anchos = largo.map((l) => Math.max(1250, Math.round((l / total) * ANCHO_CONTENIDO)));
  const ajuste = ANCHO_CONTENIDO / anchos.reduce((a, b) => a + b, 0);
  anchos = anchos.map((a) => Math.floor(a * ajuste));
  anchos[n - 1] += ANCHO_CONTENIDO - anchos.reduce((a, b) => a + b, 0);

  const borde = { style: BorderStyle.SINGLE, size: 4, color: "D0D5DB" };
  const bordes = { top: borde, bottom: borde, left: borde, right: borde };
  const centrado = celdas(lineas[1]).map((s) => /^:-+:$/.test(s));

  const fila = (valores, esEncabezado) => new TableRow({
    tableHeader: esEncabezado,
    children: valores.map((v, i) => new TableCell({
      width: { size: anchos[i], type: WidthType.DXA },
      borders: bordes,
      shading: esEncabezado ? { fill: "E9F7E4", type: ShadingType.CLEAR, color: "auto" } : undefined,
      margins: { top: 60, bottom: 60, left: 100, right: 100 },
      children: [new Paragraph({
        alignment: centrado[i] ? AlignmentType.CENTER : AlignmentType.LEFT,
        children: runsEnLinea(limpiarAutolinks(v || ""), { size: 18, bold: esEncabezado || undefined }),
      })],
    })),
  });

  return new Table({
    width: { size: ANCHO_CONTENIDO, type: WidthType.DXA },
    columnWidths: anchos,
    rows: [fila(encabezado, true), ...filas.map((f) => fila(f, false))],
  });
}

/* ---------- Conversión de un Markdown ---------- */
function convertir(md, esPrimero) {
  const salida = [];
  const lineas = md.replace(/\r/g, "").split("\n");
  let parrafo = [];
  let omitirSeccion = false;

  const cerrarParrafo = () => {
    if (parrafo.length) {
      salida.push(new Paragraph({ spacing: { after: 120 }, children: runsEnLinea(limpiarAutolinks(parrafo.join(" "))) }));
      parrafo = [];
    }
  };

  for (let i = 0; i < lineas.length; i++) {
    const l = lineas[i];

    // Encabezados
    const h = l.match(/^(#{1,4})\s+(.*)$/);
    if (h) {
      cerrarParrafo();
      const nivel = h[1].length;
      const titulo = h[2].replace(/`/g, "");
      omitirSeccion = nivel === 2 && titulo === "Contenido"; // el índice lo genera Word
      if (omitirSeccion) continue;
      ultimaFueLista = false;
      const niveles = [HeadingLevel.HEADING_1, HeadingLevel.HEADING_2, HeadingLevel.HEADING_3, HeadingLevel.HEADING_4];
      salida.push(new Paragraph({
        heading: niveles[nivel - 1],
        pageBreakBefore: nivel === 1 && !esPrimero ? true : undefined,
        children: [new TextRun(titulo)],
      }));
      continue;
    }
    if (omitirSeccion) continue;

    // Bloques de código / Mermaid
    if (l.trim().startsWith("```")) {
      cerrarParrafo();
      const sangria = l.match(/^\s*/)[0].length;
      const lenguaje = l.trim().slice(3).trim();
      const bloque = [];
      i++;
      while (i < lineas.length && !lineas[i].trim().startsWith("```")) bloque.push(lineas[i++].slice(sangria));
      if (!sangria) ultimaFueLista = false;
      if (lenguaje === "mermaid") {
        const tipo = Object.keys(MERMAID).find((k) => bloque[0].trim().startsWith(k));
        const pie = tipo === "erDiagram" ? "Diagrama entidad-relación" : "Flujo de estados de una incidencia";
        salida.push(...imagen(MERMAID[tipo], pie));
      } else {
        bloque.forEach((b, idx) => salida.push(new Paragraph({
          shading: { fill: "F4F5F7", type: ShadingType.CLEAR, color: "auto" },
          spacing: { before: idx === 0 ? 80 : 0, after: idx === bloque.length - 1 ? 160 : 0 },
          indent: { left: 200 + (sangria ? 360 : 0) },
          children: [new TextRun({ text: b || " ", font: "Consolas", size: 15 })],
        })));
      }
      continue;
    }

    // Tablas
    if (l.trim().startsWith("|")) {
      cerrarParrafo();
      const bloque = [];
      while (i < lineas.length && lineas[i].trim().startsWith("|")) bloque.push(lineas[i++]);
      i--;
      ultimaFueLista = false;
      salida.push(tabla(bloque));
      salida.push(new Paragraph({ spacing: { after: 120 }, children: [] }));
      continue;
    }

    // Imágenes
    const img = l.match(/^!\[([^\]]*)\]\(([^)]+)\)/);
    if (img) {
      cerrarParrafo();
      ultimaFueLista = false;
      salida.push(...imagen(img[2], img[1]));
      continue;
    }

    // Citas
    if (l.startsWith(">")) {
      cerrarParrafo();
      const bloque = [];
      ultimaFueLista = false;
      while (i < lineas.length && lineas[i].startsWith(">")) bloque.push(lineas[i++].replace(/^>\s?/, ""));
      i--;
      // Notas con código o listas adentro: se convierten completas (con su formato).
      if (bloque.some((b) => /^\s*```/.test(b) || /^\s*(\d+\.|[-*])\s+/.test(b))) {
        salida.push(...convertir(bloque.join("\n"), true));
        continue;
      }
      salida.push(new Paragraph({
        border: { left: { style: BorderStyle.SINGLE, size: 18, color: COLOR, space: 8 } },
        indent: { left: 240 },
        spacing: { before: 60, after: 160 },
        children: runsEnLinea(limpiarAutolinks(bloque.join(" ")), { italics: true, color: "4B5563" }),
      }));
      continue;
    }

    // Listas (viñetas y numeradas, con sangría)
    const li = l.match(/^(\s*)([-*]|\d+\.)\s+(.*)$/);
    if (li) {
      cerrarParrafo();
      // Si la lista continúa después de un bloque de código, conserva la numeración.
      if (!(li[1].length === 0 && /^\d+\./.test(li[2]) && li[2] !== "1." && ultimaFueLista)) instanciaLista++;
      while (i < lineas.length) {
        const actual = lineas[i].match(/^(\s*)([-*]|\d+\.)\s+(.*)$/);
        if (!actual) break;
        const nivel = Math.min(Math.floor(actual[1].length / 3), 2);
        const numerada = /\d+\./.test(actual[2]);
        let texto = actual[3];
        // Líneas siguientes con sangría (sin marcador ni código) continúan el mismo elemento.
        while (
          i + 1 < lineas.length &&
          /^\s{2,}\S/.test(lineas[i + 1]) &&
          !/^\s*([-*]|\d+\.)\s+/.test(lineas[i + 1]) &&
          !lineas[i + 1].trim().startsWith("```")
        ) {
          texto += " " + lineas[++i].trim();
        }
        salida.push(new Paragraph({
          numbering: numerada
            ? { reference: "numeros", level: nivel, instance: instanciaLista }
            : { reference: "vinetas", level: nivel },
          spacing: { after: 60 },
          children: runsEnLinea(limpiarAutolinks(texto)),
        }));
        i++;
        // Un bloque de código con sangría dentro de la lista se procesa aparte
        // sin cortar la numeración.
        if (i < lineas.length && lineas[i].trim().startsWith("```") && /^\s+/.test(lineas[i])) break;
        // Línea en blanco entre elementos de la misma lista.
        if (i + 1 < lineas.length && lineas[i] === "" && /^(\s*)([-*]|\d+\.)\s+/.test(lineas[i + 1])) i++;
      }
      ultimaFueLista = true;
      i--;
      continue;
    }

    if (l.trim() === "---") { cerrarParrafo(); continue; }
    if (l.trim() === "") { cerrarParrafo(); continue; }

    ultimaFueLista = false;
    parrafo.push(l.trim());
  }
  cerrarParrafo();
  return salida;
}

/* ---------- Portada ---------- */
function portada() {
  const centrado = (texto, opciones = {}, espacio = {}) => new Paragraph({
    alignment: AlignmentType.CENTER, spacing: espacio, children: [new TextRun({ text: texto, ...opciones })],
  });
  return [
    centrado("TECNOLÓGICO DE ESTUDIOS SUPERIORES DE CHIMALHUACÁN", { bold: true, size: 26, color: COLOR }, { before: 1800, after: 80 }),
    centrado("Departamento de Ciencias Básicas", { size: 24, color: "4B5563" }, { after: 1400 }),
    centrado("Sistema de Gestión de Incidencias", { bold: true, size: 52, color: "1F2933" }, { after: 200 }),
    centrado(CFG.titulo, { size: 30, color: "4B5563" }, { after: 1600 }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      border: { top: { style: BorderStyle.SINGLE, size: 12, color: COLOR, space: 12 } },
      spacing: { before: 400, after: 80 },
      children: [new TextRun({ text: CFG.contenido, size: 20, color: "4B5563" })],
    }),
    centrado("Chimalhuacán, Estado de México · Septiembre de 2026 · Base de datos MongoDB", { size: 20, color: "4B5563" }, { after: 0 }),
    new Paragraph({ children: [new PageBreak()] }),
    new Paragraph({ spacing: { after: 200 }, children: [new TextRun({ text: "Índice", bold: true, size: 36, color: COLOR })] }),
    new TableOfContents("Índice", { hyperlink: true, headingStyleRange: "1-2" }),
    new Paragraph({
      spacing: { before: 200 },
      children: [new TextRun({ text: "Si el índice aparece vacío, haz clic derecho sobre él y elige «Actualizar campos».", italics: true, size: 18, color: "6B7280" })],
    }),
  ];
}

/* ---------- Documento ---------- */
function generar(clave) {
  CFG = DOCUMENTOS[clave];
  const SALIDA = path.join(DOCS, CFG.salida);
  instanciaLista = 0; ultimaFueLista = false;
const cuerpo = [];
CFG.archivos.forEach((archivo, idx) => {
  const md = fs.readFileSync(path.join(DOCS, archivo), "utf8");
  cuerpo.push(...convertir(md, false));
});

const doc = new Document({
  creator: "Departamento de Ciencias Básicas · TESCHI",
  title: "Sistema de Gestión de Incidencias — " + CFG.titulo,
  description: CFG.descripcion,
  features: { updateFields: true },
  styles: {
    default: { document: { run: { font: "Arial", size: 21 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 36, bold: true, color: COLOR, font: "Arial" }, paragraph: { spacing: { before: 240, after: 200 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 28, bold: true, color: "1F2933", font: "Arial" }, paragraph: { spacing: { before: 320, after: 140 }, outlineLevel: 1 } },
      { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 24, bold: true, color: "374151", font: "Arial" }, paragraph: { spacing: { before: 240, after: 100 }, outlineLevel: 2 } },
      { id: "Heading4", name: "Heading 4", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 22, bold: true, color: "374151", font: "Arial" }, paragraph: { spacing: { before: 200, after: 80 }, outlineLevel: 3 } },
    ],
  },
  numbering: {
    config: [
      { reference: "vinetas", levels: [0, 1, 2].map((n) => ({
        level: n, format: LevelFormat.BULLET, text: ["•", "◦", "▪"][n], alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 360 * (n + 1), hanging: 260 } } } })) },
      { reference: "numeros", levels: [0, 1, 2].map((n) => ({
        level: n, format: [LevelFormat.DECIMAL, LevelFormat.LOWER_LETTER, LevelFormat.LOWER_ROMAN][n], text: `%${n + 1}.`,
        alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360 * (n + 1), hanging: 300 } } } })) },
    ],
  },
  sections: [
    {
      properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 } }, titlePage: true },
      headers: {
        default: new Header({ children: [new Paragraph({
          alignment: AlignmentType.RIGHT,
          border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: "D0D5DB", space: 4 } },
          children: [new TextRun({ text: "Sistema de Gestión de Incidencias · TESCHI", size: 16, color: "6B7280" })],
        })] }),
        first: new Header({ children: [new Paragraph({ children: [] })] }),
      },
      footers: {
        default: new Footer({ children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [new TextRun({ children: ["Página ", PageNumber.CURRENT, " de ", PageNumber.TOTAL_PAGES], size: 16, color: "6B7280" })],
        })] }),
        first: new Footer({ children: [new Paragraph({ children: [] })] }),
      },
      children: [...portada(), ...cuerpo],
    },
  ],
});

return Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(SALIDA, buf);
  console.log("ok", SALIDA, Math.round(buf.length / 1024) + " KB");
});
}

const pedido = process.argv[2];
const claves = pedido ? [pedido] : Object.keys(DOCUMENTOS);
(async () => { for (const c of claves) await generar(c); })();

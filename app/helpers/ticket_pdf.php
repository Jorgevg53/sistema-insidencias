<?php

/*
|--------------------------------------------------------------------------
| TICKET DE INCIDENCIA EN PDF
|--------------------------------------------------------------------------
| Genera el comprobante que se entrega al solicitante, con los mismos
| datos del ticket en papel del Departamento más los del sistema.
| Usa FPDF (app/lib/fpdf), que no requiere Composer.
*/

require_once __DIR__ . "/../lib/fpdf/fpdf.php";
require_once __DIR__ . "/../models/Incidencia.php";

class TicketPdf extends FPDF
{
    public $folio = "";

    /*
     * FPDF usa Windows-1252 con las fuentes estándar: se convierte
     * el texto UTF-8 para que los acentos y la ñ se vean bien.
     */
    public static function t($texto)
    {
        $texto = str_replace(["→", "“", "”"], ["->", '"', '"'], (string) ($texto ?? ""));

        $convertido = @iconv("UTF-8", "windows-1252//TRANSLIT", $texto);

        return $convertido !== false ? $convertido : mb_convert_encoding($texto, "Windows-1252", "UTF-8");
    }

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont("Helvetica", "", 7);
        $this->SetTextColor(120);
        $this->Cell(
            0,
            4,
            self::t("Sistema de Gestión de Incidencias · " . $this->folio . " · Página " . $this->PageNo() . " de {nb}"),
            0,
            0,
            "C"
        );
    }

    /*
     * Título de sección con fondo gris.
     */
    public function seccion($titulo)
    {
        $this->Ln(1.5);
        $this->SetFont("Helvetica", "B", 9);
        $this->SetFillColor(236, 238, 241);
        $this->SetTextColor(40);
        $this->Cell(0, 5.5, self::t("  " . mb_strtoupper($titulo)), 0, 1, "L", true);
        $this->Ln(1);
    }

    /*
     * Renglón "Etiqueta: valor" (el valor se ajusta a varias líneas).
     */
    const ANCHO_ETIQUETA = 45;

    public function campo($etiqueta, $valor, $anchoEtiqueta = self::ANCHO_ETIQUETA)
    {
        $this->SetFont("Helvetica", "B", 10);
        $this->SetTextColor(60);
        $this->Cell($anchoEtiqueta, 5.5, self::t($etiqueta . ":"), 0, 0);

        $this->SetFont("Helvetica", "", 10);
        $this->SetTextColor(20);
        $this->MultiCell(0, 5.5, self::t($valor !== null && $valor !== "" ? $valor : "No especificado"), 0, "L");
    }

    /*
     * Dos campos en la misma línea (para datos cortos).
     */
    public function camposDobles($etiqueta1, $valor1, $etiqueta2, $valor2)
    {
        $mitad = ($this->GetPageWidth() - $this->lMargin - $this->rMargin) / 2;

        foreach ([[$etiqueta1, $valor1], [$etiqueta2, $valor2]] as $i => [$etiqueta, $valor]) {
            $this->SetFont("Helvetica", "B", 10);
            $this->SetTextColor(60);
            $this->Cell(self::ANCHO_ETIQUETA, 5.5, self::t($etiqueta . ":"), 0, 0);
            $this->SetFont("Helvetica", "", 10);
            $this->SetTextColor(20);
            $this->Cell($mitad - self::ANCHO_ETIQUETA, 5.5, self::t($valor !== null && $valor !== "" ? $valor : "No especificado"), 0, $i === 1 ? 1 : 0);
        }
    }
}

/*
 * Devuelve el PDF como texto.
 *   $incidencia -> arreglo de Incidencia::buscarPorId() (con datos del solicitante)
 *   $datos      -> ["evidencias" => int, "impreso_por" => string]
 */
function generarTicketPdf(array $incidencia, array $datos = [])
{
    $institucion = require __DIR__ . "/../config/institucion.php";

    $pdf = new TicketPdf("P", "mm", "Letter");
    $pdf->folio = $incidencia["folio"];
    $pdf->SetTitle("Ticket " . $incidencia["folio"], true);
    $pdf->SetAuthor($institucion["departamento"], true);
    $pdf->SetMargins(18, 15, 18);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchoUtil = $pdf->GetPageWidth() - 36;

    /*
    |------------------------------------------------------------------
    | ENCABEZADO
    |------------------------------------------------------------------
    */

    foreach ($institucion["logos"] as $logo) {
        if (is_file($logo)) {
            $pdf->Image($logo, 18, 13, 24);
            break;
        }
    }

    $pdf->SetTextColor(20);
    $pdf->SetFont("Helvetica", "B", 13);
    $pdf->SetX(45);
    $pdf->MultiCell($anchoUtil - 54, 6, TicketPdf::t($institucion["nombre"]), 0, "C");
    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->SetX(45);
    $pdf->MultiCell($anchoUtil - 54, 5, TicketPdf::t($institucion["direccion"] . "\n" . $institucion["ciudad"]), 0, "C");
    $pdf->SetFont("Helvetica", "", 9);
    $pdf->SetX(45);
    $pdf->Cell($anchoUtil - 54, 5, TicketPdf::t($institucion["departamento"] . " · Ticket de incidencia"), 0, 1, "C");

    $pdf->Ln(3);
    $pdf->SetDrawColor(122, 31, 61);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(18, $pdf->GetY(), 18 + $anchoUtil, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(180);
    $pdf->Ln(4);

    /*
    |------------------------------------------------------------------
    | NO. DE TICKET, FECHA Y HORA
    |------------------------------------------------------------------
    */

    $registro = new DateTime($incidencia["fecha_registro"]);

    $yInicio = $pdf->GetY();

    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->SetTextColor(60);
    $pdf->Cell(25, 6, TicketPdf::t("Folio:"), 0, 0);
    $pdf->SetFont("Helvetica", "", 10);
    $pdf->SetTextColor(20);
    $pdf->Cell(70, 6, TicketPdf::t($incidencia["folio"]), 0, 1);

    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->SetTextColor(60);
    $pdf->Cell(25, 6, TicketPdf::t("Fecha:"), 0, 0);
    $pdf->SetFont("Helvetica", "", 10);
    $pdf->SetTextColor(20);
    $pdf->Cell(70, 6, $registro->format("d/m/Y"), 0, 1);

    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->SetTextColor(60);
    $pdf->Cell(25, 6, TicketPdf::t("Hora:"), 0, 0);
    $pdf->SetFont("Helvetica", "", 10);
    $pdf->SetTextColor(20);
    $pdf->Cell(70, 6, $registro->format("h:i A"), 0, 1);

    // Número de ticket grande a la derecha.
    $pdf->SetXY(18 + $anchoUtil - 60, $yInicio);
    $pdf->SetDrawColor(122, 31, 61);
    $pdf->SetFont("Helvetica", "", 9);
    $pdf->SetTextColor(122, 31, 61);
    $pdf->Cell(60, 6, TicketPdf::t("No. Ticket"), "LTR", 2, "C");
    $pdf->SetFont("Helvetica", "B", 20);
    $pdf->Cell(60, 12, (string) (int) $incidencia["id"], "LBR", 1, "C");
    $pdf->SetDrawColor(180);
    $pdf->SetTextColor(20);

    $pdf->SetY(max($pdf->GetY(), $yInicio + 18) + 2);

    /*
    |------------------------------------------------------------------
    | DATOS DEL SOLICITANTE
    |------------------------------------------------------------------
    */

    $esEstudiante = $incidencia["rol_solicitante"] === "Estudiante";

    $pdf->seccion("Datos del solicitante");

    $pdf->campo(
        $incidencia["rol_solicitante"] === "Docente" ? "Nombre del docente" : "Nombre del solicitante",
        trim($incidencia["nombre"] . " " . $incidencia["apellido_paterno"] . " " . $incidencia["apellido_materno"])
    );
    $pdf->campo("Carrera", $incidencia["carrera"] ?: $incidencia["carrera_usuario"]);
    $pdf->camposDobles(
        $esEstudiante ? "Matrícula" : "No. empleado",
        $incidencia["matricula"],
        "Tipo",
        $incidencia["rol_solicitante"]
    );
    $pdf->campo("Teléfono", $incidencia["telefono_contacto"] ?: $incidencia["telefono_usuario"]);
    $pdf->campo("Correo", $incidencia["correo"]);

    /*
    |------------------------------------------------------------------
    | SOLICITUD
    |------------------------------------------------------------------
    */

    $pdf->seccion("Solicitud");

    $pdf->campo("Solicitud", $incidencia["titulo"]);
    $pdf->camposDobles("Categoría", $incidencia["categoria"], "Prioridad", $incidencia["prioridad"]);
    $pdf->campo("Ubicación", $incidencia["ubicacion"]);

    $pdf->Ln(1);
    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->SetTextColor(60);
    $pdf->Cell(0, 6, TicketPdf::t("Descripción breve:"), 0, 1);
    $pdf->SetFont("Helvetica", "", 10);
    $pdf->SetTextColor(20);
    $pdf->MultiCell(0, 5, TicketPdf::t($incidencia["descripcion"]), 1, "L");

    /*
    |------------------------------------------------------------------
    | TIEMPO ESTIMADO Y ESTADO
    |------------------------------------------------------------------
    */

    $dias = (int) $incidencia["dias_atencion"];
    $compromiso = Incidencia::fechaCompromiso($incidencia["fecha_registro"], $dias);

    $pdf->Ln(2);
    $pdf->SetFont("Helvetica", "B", 11);
    $pdf->Cell(
        0,
        6,
        TicketPdf::t(
            "Tiempo estimado de atención: " . $dias . ($dias === 1 ? " día hábil" : " días hábiles")
            . " (a más tardar el " . $compromiso->format("d/m/Y") . ")"
        ),
        0,
        1,
        "C"
    );

    $pdf->seccion("Estado al " . date("d/m/Y h:i A"));

    $pdf->camposDobles(
        "Estado",
        $incidencia["estado"],
        "Responsable",
        $incidencia["responsable"] ? trim($incidencia["responsable"]) : "Sin asignar"
    );
    $pdf->camposDobles(
        "Evidencias",
        (int) ($datos["evidencias"] ?? 0) . " archivo(s)",
        "Cierre",
        !empty($incidencia["fecha_cierre"]) ? (new DateTime($incidencia["fecha_cierre"]))->format("d/m/Y h:i A") : "Pendiente"
    );

    /*
    |------------------------------------------------------------------
    | LEYENDAS
    |------------------------------------------------------------------
    */

    $pdf->Ln(2);
    $pdf->SetFont("Helvetica", "B", 11);
    $pdf->Cell(0, 6, TicketPdf::t($institucion["leyenda_garantia"]), 0, 1, "C");
    $pdf->SetFont("Helvetica", "", 9);
    $pdf->MultiCell(0, 5, TicketPdf::t($institucion["leyenda_aclaracion"]), 0, "C");

    /*
    |------------------------------------------------------------------
    | FIRMAS Y SELLO
    |------------------------------------------------------------------
    */

    // Si no cabe el bloque de firmas, se pasa completo a otra página.
    if ($pdf->GetY() + 52 > $pdf->GetPageHeight() - 14) {
        $pdf->AddPage();
    }

    $pdf->Ln(15);

    $y = $pdf->GetY();
    $anchoFirma = 62;
    $xIzquierda = 18;
    $xDerecha = 18 + $anchoUtil - $anchoFirma;

    // Espacio para el sello del Departamento.
    $pdf->SetDrawColor(200);
    $pdf->SetLineWidth(0.2);
    $ladoSello = 28;
    $xSello = 18 + ($anchoUtil - $ladoSello) / 2;
    $pdf->Rect($xSello, $y - 12, $ladoSello, $ladoSello);
    $pdf->SetXY($xSello, $y + 11);
    $pdf->SetFont("Helvetica", "", 7);
    $pdf->SetTextColor(150);
    $pdf->Cell($ladoSello, 4, TicketPdf::t("Sello"), 0, 0, "C");

    $pdf->SetDrawColor(60);
    $pdf->SetTextColor(20);
    $pdf->Line($xIzquierda, $y + 12, $xIzquierda + $anchoFirma, $y + 12);
    $pdf->Line($xDerecha, $y + 12, $xDerecha + $anchoFirma, $y + 12);

    // Nombre impreso de la persona atendida sobre la línea.
    $pdf->SetXY($xDerecha, $y + 6);
    $pdf->SetFont("Helvetica", "", 9);
    $pdf->Cell($anchoFirma, 5, TicketPdf::t(trim($incidencia["nombre"] . " " . $incidencia["apellido_paterno"] . " " . $incidencia["apellido_materno"])), 0, 0, "C");

    $pdf->SetFont("Helvetica", "", 9);
    $pdf->SetXY($xIzquierda, $y + 13);
    $pdf->Cell($anchoFirma, 5, TicketPdf::t($institucion["firma_departamento"]), 0, 0, "C");
    $pdf->SetXY($xDerecha, $y + 13);
    $pdf->Cell($anchoFirma, 5, TicketPdf::t($institucion["firma_atendido"]), 0, 1, "C");

    $pdf->Ln(8);
    $pdf->SetFont("Helvetica", "B", 11);
    $pdf->Cell(0, 6, TicketPdf::t($institucion["despedida"]), 0, 1, "C");

    if (!empty($datos["impreso_por"])) {
        $pdf->SetFont("Helvetica", "", 7);
        $pdf->SetTextColor(130);
        $pdf->Cell(0, 5, TicketPdf::t("Impreso por " . $datos["impreso_por"] . " el " . date("d/m/Y h:i A")), 0, 1, "C");
    }

    return $pdf->Output("S");
}

<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Escritor mínimo de libros .xlsx (varias hojas, encabezado en negrita y
 * ancho de columnas automático) sin dependencias externas: un .xlsx es un
 * zip de XML. La primera fila de cada hoja se trata como encabezado.
 */
class XlsxWriter
{
    /**
     * Con $presentacion el libro sale con letra más grande, celdas centradas,
     * bordes suaves, filas alternadas y filtros en el encabezado.
     *
     * @param  array<string, array<int, array<int, string|int|float|null>>>  $hojas
     */
    public static function guardar(string $ruta, array $hojas, bool $presentacion = false): void
    {
        if ($hojas === []) {
            throw new RuntimeException('El libro necesita al menos una hoja.');
        }

        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $nombres = self::nombresDeHoja(array_keys($hojas));
        $total = count($nombres);

        $overrides = '';
        $sheets = '';
        $relaciones = '';

        foreach ($nombres as $i => $nombre) {
            $n = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheets .= '<sheet name="'.self::xml($nombre).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relaciones .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relaciones.'<Relationship Id="rId'.($total + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $presentacion ? self::estilosDePresentacion() : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF16468E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');

        foreach (array_values($hojas) as $i => $filas) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', self::hoja(array_values($filas), $presentacion));
        }

        $zip->close();
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $filas
     */
    private static function hoja(array $filas, bool $presentacion = false): string
    {
        $anchos = [];
        $datos = '';

        foreach ($filas as $r => $fila) {
            $celdas = '';

            foreach (array_values($fila) as $c => $valor) {
                $referencia = self::columna($c).($r + 1);
                $estilo = $r === 0 ? ' s="1"' : ($presentacion ? ($r % 2 === 0 ? ' s="3"' : ' s="2"') : '');
                $largo = is_string($valor) ? mb_strlen($valor) : strlen((string) $valor);
                $anchos[$c] = $presentacion
                    ? max($anchos[$c] ?? 10, min(48, (int) round($largo * 1.2) + ($r === 0 ? 6 : 4)))
                    : max($anchos[$c] ?? 8, min(60, $largo + 2));

                if ($valor === null || $valor === '') {
                    $celdas .= $estilo === '' ? '' : '<c r="'.$referencia.'"'.$estilo.'/>';
                } elseif (is_int($valor) || is_float($valor)) {
                    $celdas .= '<c r="'.$referencia.'"'.$estilo.'><v>'.$valor.'</v></c>';
                } else {
                    $celdas .= '<c r="'.$referencia.'"'.$estilo.' t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $valor).'</t></is></c>';
                }
            }

            $altura = $presentacion ? ($r === 0 ? ' ht="34" customHeight="1"' : ' ht="21" customHeight="1"') : '';
            $datos .= '<row r="'.($r + 1).'"'.$altura.'>'.$celdas.'</row>';
        }

        $columnas = '';
        foreach ($anchos as $c => $ancho) {
            $columnas .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$ancho.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($columnas === '' ? '' : '<cols>'.$columnas.'</cols>')
            .'<sheetData>'.$datos.'</sheetData>'
            .($presentacion && $anchos !== [] ? '<autoFilter ref="A1:'.self::columna(count($anchos) - 1).count($filas).'"/>' : '')
            .'</worksheet>';
    }

    /**
     * Estilos del modo de presentación: 1 = encabezado, 2 y 3 = filas
     * alternadas, todos centrados y con borde suave.
     */
    private static function estilosDePresentacion(): string
    {
        $centrado = '<alignment horizontal="center" vertical="center"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="12"/><color rgb="FF1E293B"/><name val="Calibri"/></font><font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF16468E"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F6FD"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD3DCEB"/></left><right style="thin"><color rgb="FFD3DCEB"/></right><top style="thin"><color rgb="FFD3DCEB"/></top><bottom style="thin"><color rgb="FFD3DCEB"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'.$centrado.'</xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$centrado.'</xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'.$centrado.'</xf></cellXfs></styleSheet>';
    }

    /**
     * Excel limita el nombre a 31 caracteres, sin []:*?/\ y sin repetirse.
     *
     * @param  array<int, string>  $nombres
     * @return array<int, string>
     */
    private static function nombresDeHoja(array $nombres): array
    {
        $usados = [];

        return array_map(function (string $nombre) use (&$usados): string {
            $limpio = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $nombre)) ?: 'Hoja', 0, 31);
            $base = $limpio;
            $i = 2;

            while (in_array(mb_strtolower($limpio), $usados, true)) {
                $limpio = mb_substr($base, 0, 28).' '.$i++;
            }

            $usados[] = mb_strtolower($limpio);

            return $limpio;
        }, $nombres);
    }

    private static function columna(int $indice): string
    {
        $letras = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26).$letras;
        }

        return $letras;
    }

    private static function xml(string $texto): string
    {
        $texto = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $texto) ?? '';

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

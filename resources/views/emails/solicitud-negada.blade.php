<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Solicitud negada</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:32px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(13,45,107,0.10);">

          <!-- Header -->
          <tr>
            <td style="background:#0D2D6B;padding:30px 40px;text-align:center;">
              <img src="{{ $message->embed(public_path('images/logo-w.png')) }}" alt="Clínica Santa Bárbara" style="height:52px;margin:0 auto 12px;display:block;">
              <p style="margin:0;color:#ffffff;font-size:15px;font-weight:bold;letter-spacing:0.5px;">Central de Referencia</p>
              <p style="margin:3px 0 0;color:#a0b8e8;font-size:12px;">Clínica Santa Bárbara · Alta Complejidad</p>
            </td>
          </tr>

          <!-- Héroe de estado -->
          <tr>
            <td style="background:#fef2f2;padding:26px 40px;text-align:center;border-bottom:3px solid #dc2626;">
              <table cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 10px;">
                <tr>
                  <td style="width:44px;height:44px;background:#dc2626;border-radius:50%;text-align:center;vertical-align:middle;font-size:20px;color:#ffffff;font-weight:bold;">✕</td>
                </tr>
              </table>
              <p style="margin:0;font-size:20px;font-weight:bold;color:#b91c1c;letter-spacing:0.5px;">SOLICITUD NEGADA</p>
              <p style="margin:6px 0 0;font-size:14px;color:#7f1d1d;">
                {{ $solicitud->primer_nombre }} {{ $solicitud->segundo_nombre }} {{ $solicitud->primer_apellido }} {{ $solicitud->segundo_apellido }} — {{ $solicitud->especialidad_requerida }}
              </p>
            </td>
          </tr>

          <!-- Motivo de no ingreso: solo cuando el paciente no llegó. En una negación normal
               el grupo/motivo es interno y la IPS solo ve las observaciones. -->
          @if(str_starts_with((string) $solicitud->motivo_negacion, 'No ingreso:'))
          <tr>
            <td style="padding:28px 40px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;">
                <tr>
                  <td style="padding:18px 24px;">
                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;color:#b91c1c;text-transform:uppercase;letter-spacing:1px;">Motivo de no ingreso</p>
                    <p style="margin:0;font-size:14px;color:#7f1d1d;line-height:1.6;">{{ trim(substr((string) $solicitud->motivo_negacion, strlen('No ingreso:'))) }}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          @endif

          <!-- Cuerpo -->
          <tr>
            <td style="padding:26px 40px 36px;">
              <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.7;">
                Estimados representantes de <strong>{{ $solicitud->clinica->nombre ?? $solicitud->institucion_remitente ?? 'la institución remitente' }}</strong>,
                lamentamos informar que la solicitud de referencia fue <strong style="color:#b91c1c;">negada</strong> por nuestra institución.
              </p>

              <!-- Datos del paciente -->
              <p style="margin:0 0 8px;font-size:11px;font-weight:bold;color:#0D2D6B;text-transform:uppercase;letter-spacing:1px;">
                Información del paciente
              </p>
              <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:10px;overflow:hidden;border:1px solid #e0e8f0;margin-bottom:20px;">
                <tr style="background:#f8fafc;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;width:42%;border-bottom:1px solid #e8edf2;">Nombre del paciente</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;font-weight:700;border-bottom:1px solid #e8edf2;">{{ $solicitud->primer_nombre }} {{ $solicitud->segundo_nombre }} {{ $solicitud->primer_apellido }} {{ $solicitud->segundo_apellido }}</td>
                </tr>
                <tr style="background:#ffffff;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e8edf2;">Tipo y número de documento</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;border-bottom:1px solid #e8edf2;">{{ $solicitud->tipo_documento }} {{ $solicitud->numero_documento }}</td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e8edf2;">Aseguradora (EPS)</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;border-bottom:1px solid #e8edf2;">{{ $solicitud->eps }}</td>
                </tr>
                <tr style="background:#ffffff;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e8edf2;">Especialidad</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;font-weight:700;border-bottom:1px solid #e8edf2;">{{ $solicitud->especialidad_requerida }}</td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;">Estado de la solicitud</td>
                  <td style="padding:10px 16px;font-size:13px;color:#b91c1c;font-weight:700;">NEGADA</td>
                </tr>
              </table>

              @if($solicitud->hora_respuesta || $solicitud->nombre_quien_responde)
              <p style="margin:0 0 8px;font-size:11px;font-weight:bold;color:#0D2D6B;text-transform:uppercase;letter-spacing:1px;">
                Datos de la respuesta
              </p>
              <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:10px;overflow:hidden;border:1px solid #e0e8f0;margin-bottom:20px;">
                @if($solicitud->nombre_quien_responde)
                <tr style="background:#f8fafc;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;width:42%;border-bottom:1px solid #e8edf2;">Respondió</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;border-bottom:1px solid #e8edf2;">{{ $solicitud->nombre_quien_responde }}</td>
                </tr>
                @endif
                @if($solicitud->hora_respuesta)
                <tr style="background:#ffffff;">
                  <td style="padding:10px 16px;font-size:13px;color:#64748b;">Hora de respuesta</td>
                  <td style="padding:10px 16px;font-size:13px;color:#1e2d55;">{{ $solicitud->hora_respuesta }}</td>
                </tr>
                @endif
              </table>
              @endif

              @if($solicitud->observaciones_respuesta)
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-left:3px solid #0D2D6B;border-radius:0 8px 8px 0;margin-bottom:20px;">
                <tr>
                  <td style="padding:12px 16px;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:bold;color:#0D2D6B;text-transform:uppercase;letter-spacing:0.5px;">Observaciones</p>
                    <p style="margin:0;font-size:13px;color:#475569;line-height:1.6;">{{ $solicitud->observaciones_respuesta }}</p>
                  </td>
                </tr>
              </table>
              @endif

              <p style="margin:0;font-size:13px;color:#64748b;line-height:1.7;">
                Si tiene preguntas sobre esta decisión, comuníquese con la Central de Referencia de la Clínica Santa Bárbara.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;padding:22px 40px;text-align:center;border-top:1px solid #e8edf2;">
              <p style="margin:0 0 4px;font-size:13px;font-weight:bold;color:#0D2D6B;">Cordialmente,</p>
              <p style="margin:0 0 10px;font-size:13px;color:#475569;">Central de Referencia — Clínica Santa Bárbara</p>
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                © {{ date('Y') }} Clínica Santa Bárbara · Este correo fue generado automáticamente, por favor no responda.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>

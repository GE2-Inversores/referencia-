<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Su correo fue redirigido</title>
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
            <td style="background:#eff6ff;padding:26px 40px;text-align:center;border-bottom:3px solid #2563eb;">
              <p style="margin:0;font-size:20px;font-weight:bold;color:#1d4ed8;letter-spacing:0.5px;">SU CORREO FUE REDIRIGIDO</p>
              <p style="margin:6px 0 0;font-size:14px;color:#1e3a8a;">
                Respuesta a su correo: {{ $mensaje->asunto }}
              </p>
            </td>
          </tr>

          <!-- Mensaje -->
          <tr>
            <td style="padding:28px 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1px solid #93c5fd;border-radius:12px;">
                <tr>
                  <td style="padding:18px 24px;">
                    <p style="margin:0;font-size:14px;color:#1e3a8a;line-height:1.7;">
                      Su mensaje llegó a la central de referencia de Clínica Santa Bárbara, pero
                      la solicitud no corresponde a esta entidad. Lo reenviamos al correo del
                      destinatario correcto para que pueda ser gestionado oportunamente.
                    </p>
                    <p style="margin:12px 0 0;font-size:13px;color:#1e40af;">
                      No necesita realizar ninguna acción adicional.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Firma -->
          <tr>
            <td style="padding:0 40px 26px;">
              <p style="margin:0;font-size:13px;color:#475569;line-height:1.7;">
                Cordialmente,<br>
                <strong style="color:#0D2D6B;">Central de Referencia · Clínica Santa Bárbara</strong>
                @if(filled($quienResponde ?? null))<br><span style="font-size:12px;color:#64748b;">Gestionado por {{ $quienResponde }}</span>@endif
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#f8fafc;padding:16px 40px;text-align:center;border-top:1px solid #e2e8f0;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">Este es un mensaje automático de la Central de Referencia. Por favor no responda a este correo.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Correo reenviado</title>
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
              <table cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 10px;">
                <tr>
                  <td style="width:44px;height:44px;background:#2563eb;border-radius:50%;text-align:center;vertical-align:middle;font-size:20px;color:#ffffff;font-weight:bold;">➤</td>
                </tr>
              </table>
              <p style="margin:0;font-size:20px;font-weight:bold;color:#1d4ed8;letter-spacing:0.5px;">CORREO REENVIADO</p>
              <p style="margin:6px 0 0;font-size:14px;color:#1e3a8a;">
                Este mensaje llegó a nuestra central de referencia y lo reenviamos a ustedes para su gestión.
              </p>
            </td>
          </tr>

          <!-- Nota del equipo -->
          @if(filled($nota ?? null))
          <tr>
            <td style="padding:28px 40px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1px solid #93c5fd;border-radius:12px;">
                <tr>
                  <td style="padding:18px 24px;">
                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;color:#1d4ed8;text-transform:uppercase;letter-spacing:1px;">Nota de la Central</p>
                    <p style="margin:0;font-size:14px;color:#1e3a8a;line-height:1.6;">{{ $nota }}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          @endif

          <!-- Correo original -->
          <tr>
            <td style="padding:28px 40px;">
              <p style="margin:0 0 6px;font-size:11px;font-weight:bold;color:#475569;text-transform:uppercase;letter-spacing:1px;">Correo original</p>
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                <tr>
                  <td style="padding:18px 24px;">
                    <p style="margin:0;font-size:12px;color:#64748b;">
                      <strong>De:</strong> {{ $mensaje->remitente }}<br>
                      <strong>Asunto:</strong> {{ $mensaje->asunto }}<br>
                      <strong>Fecha:</strong> {{ $mensaje->recibido_en?->format('d/m/Y h:i a') }}
                    </p>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px dashed #cbd5e1;font-size:13px;color:#334155;line-height:1.7;white-space:pre-wrap;">{{ $mensaje->cuerpo_texto }}</div>
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
                @if(filled($quienResponde ?? null))<br><span style="font-size:12px;color:#64748b;">Reenviado por {{ $quienResponde }}</span>@endif
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#f8fafc;padding:16px 40px;text-align:center;border-top:1px solid #e2e8f0;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">Este es un mensaje automático de la Central de Referencia. Los archivos adjuntos del correo original van incluidos en este envío.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>

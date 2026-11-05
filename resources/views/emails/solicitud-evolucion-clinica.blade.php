<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Solicitud de evolución del paciente</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:28px 0;">
    <tr><td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(13,45,107,.10);">

        <tr><td style="background:linear-gradient(135deg,#0D2D6B 0%,#16468E 55%,#1e3a7a 100%);padding:32px 40px 24px;text-align:center;">
          <img src="{{ $message->embed(public_path('images/logo-w.png')) }}" alt="Clínica Santa Bárbara" style="height:48px;margin:0 auto 10px;display:block;">
          <p style="margin:0;color:#a0b8e8;font-size:13px;">Sistema de Referencia</p>
        </td></tr>

        <tr><td style="background:linear-gradient(90deg,#dbeafe,#bfdbfe);padding:14px 40px;border-bottom:3px solid #2563eb;text-align:center;">
          <p style="margin:0;color:#1e40af;font-size:14px;font-weight:bold;">&#128221; Solicitud de evolución médica actualizada</p>
        </td></tr>

        <tr><td style="padding:28px 40px 8px;">
          <p style="margin:0 0 6px;color:#0D2D6B;font-size:15px;font-weight:bold;">Solicitud de evolución</p>
          <p style="margin:0 0 12px;color:#334155;font-size:13px;line-height:1.7;">Cordial saludo.</p>
          <p style="margin:0 0 12px;color:#334155;font-size:13px;line-height:1.7;">El paciente se encuentra pendiente de disponibilidad de cupo para la generación del código de aceptación. Por lo anterior, agradecemos continuar enviando la evolución médica actualizada, con el fin de realizar el seguimiento correspondiente y gestionar la asignación del cupo.</p>
          @if(filled($mensaje))
          <div style="margin:0 0 16px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #2563eb;border-radius:10px;">
            <p style="margin:0 0 6px;color:#1e40af;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">Mensaje del equipo de referencia</p>
            <p style="margin:0;color:#334155;font-size:13px;line-height:1.7;white-space:pre-line;">{{ $mensaje }}</p>
          </div>
          @endif
          <p style="margin:0 0 22px;color:#334155;font-size:13px;line-height:1.7;">Muchas gracias por su colaboración.</p>
          <p style="margin:0 0 10px;color:#64748b;font-size:12px;">Referencia <strong style="color:#16468E;">ID{{ str_pad((string) $solicitud->id, 6, '0', STR_PAD_LEFT) }}</strong></p>

          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e0e8f0;border-radius:10px;overflow:hidden;margin-bottom:20px;">
            <tr style="background:#f7f9fc;"><td style="padding:9px 16px;color:#64748b;font-size:12px;width:38%;">Paciente</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;font-weight:bold;">{{ $solicitud->primer_nombre }} {{ $solicitud->segundo_nombre }} {{ $solicitud->primer_apellido }} {{ $solicitud->segundo_apellido }}</td></tr>
            <tr><td style="padding:9px 16px;color:#64748b;font-size:12px;">Documento</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;">{{ $solicitud->tipo_documento }} {{ $solicitud->numero_documento }}</td></tr>
            <tr style="background:#f7f9fc;"><td style="padding:9px 16px;color:#64748b;font-size:12px;">Especialidad</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;">{{ $solicitud->especialidad_requerida }}</td></tr>
            <tr><td style="padding:9px 16px;color:#64748b;font-size:12px;">Servicio actual</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;">{{ $solicitud->servicio_ubicacion_actual ?? '—' }}</td></tr>
            @if($solicitud->urgencia_vital)<tr style="background:#f7f9fc;"><td style="padding:9px 16px;color:#64748b;font-size:12px;">Urgencia</td><td style="padding:9px 16px;color:#b91c1c;font-size:13px;font-weight:bold;">&#9888; Urgencia vital</td></tr>@endif
          </table>

          <p style="margin:0 0 6px;color:#64748b;font-size:13px;line-height:1.6;">Puede registrar la evolución con el botón de abajo: abre el módulo de Evolución con este paciente ya cargado.</p>
        </td></tr>

        <tr><td style="padding:8px 40px 28px;text-align:center;">
          <a href="{{ $enlace }}" style="display:inline-block;background:linear-gradient(135deg,#0D2D6B,#16468E);color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:14px 36px;border-radius:10px;box-shadow:0 4px 14px rgba(13,45,107,.25);">&#128221; Registrar evolución</a>
        </td></tr>

        <tr><td style="background:#f7f9fc;padding:18px 40px;text-align:center;border-top:1px solid #e8edf2;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.5;">Mensaje automático del Sistema de Referencia Santa Bárbara.<br>Si el botón no abre, copie este enlace en su navegador:<br>{{ $enlace }}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>

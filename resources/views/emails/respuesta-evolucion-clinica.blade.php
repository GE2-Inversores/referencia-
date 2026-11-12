<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Respuesta a la evolución del paciente</title>
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
          <p style="margin:0;color:#1e40af;font-size:14px;font-weight:bold;">&#128172; Respuesta del equipo de referencia a su evolución</p>
        </td></tr>

        <tr><td style="padding:28px 40px 8px;">
          <p style="margin:0 0 12px;color:#334155;font-size:13px;line-height:1.7;">Cordial saludo.</p>
          <p style="margin:0 0 16px;color:#334155;font-size:13px;line-height:1.7;">El equipo de referencia respondió a la evolución que registró para este paciente:</p>

          <div style="margin:0 0 16px;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #94a3b8;border-radius:10px;">
            <p style="margin:0 0 6px;color:#64748b;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">Su evolución · {{ $respuesta->evolucion?->created_at?->format('d/m/Y H:i') }}</p>
            <p style="margin:0;color:#475569;font-size:13px;line-height:1.7;white-space:pre-line;">{{ \Illuminate\Support\Str::limit((string) $respuesta->evolucion?->nota, 600) }}</p>
          </div>

          <div style="margin:0 0 20px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #2563eb;border-radius:10px;">
            <p style="margin:0 0 6px;color:#1e40af;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">Respuesta de referencia@if(filled($respuesta->respondido_por)) · {{ $respuesta->respondido_por }}@endif</p>
            <p style="margin:0;color:#334155;font-size:13px;line-height:1.7;white-space:pre-line;">{{ $respuesta->mensaje }}</p>
          </div>

          <p style="margin:0 0 10px;color:#64748b;font-size:12px;">Referencia <strong style="color:#16468E;">ID{{ str_pad((string) $solicitud->id, 6, '0', STR_PAD_LEFT) }}</strong></p>

          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e0e8f0;border-radius:10px;overflow:hidden;margin-bottom:20px;">
            <tr style="background:#f7f9fc;"><td style="padding:9px 16px;color:#64748b;font-size:12px;width:38%;">Paciente</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;font-weight:bold;">{{ $solicitud->primer_nombre }} {{ $solicitud->segundo_nombre }} {{ $solicitud->primer_apellido }} {{ $solicitud->segundo_apellido }}</td></tr>
            <tr><td style="padding:9px 16px;color:#64748b;font-size:12px;">Documento</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;">{{ $solicitud->tipo_documento }} {{ $solicitud->numero_documento }}</td></tr>
            <tr style="background:#f7f9fc;"><td style="padding:9px 16px;color:#64748b;font-size:12px;">Especialidad</td><td style="padding:9px 16px;color:#1a1a2e;font-size:13px;">{{ $solicitud->especialidad_requerida }}</td></tr>
          </table>

          <p style="margin:0 0 6px;color:#64748b;font-size:13px;line-height:1.6;">También puede ver esta respuesta en el módulo de Evolución, donde queda junto a su nota.</p>
        </td></tr>

        <tr><td style="padding:8px 40px 28px;text-align:center;">
          <a href="{{ $enlace }}" style="display:inline-block;background:linear-gradient(135deg,#0D2D6B,#16468E);color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:14px 36px;border-radius:10px;box-shadow:0 4px 14px rgba(13,45,107,.25);">&#128221; Ver en el módulo de Evolución</a>
        </td></tr>

        <tr><td style="background:#f7f9fc;padding:18px 40px;text-align:center;border-top:1px solid #e8edf2;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.5;">Mensaje del Sistema de Referencia Santa Bárbara.<br>Si el botón no abre, copie este enlace en su navegador:<br>{{ $enlace }}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>

<template>
  <div class="space-y-5 max-w-2xl">
    <!-- Selector de driver -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1.5">Servicio de correo</label>
      <el-select v-model="form.mail_mailer" placeholder="Seleccionar servicio" class="w-full">
        <el-option label="SMTP (Genérico)" value="smtp" />
        <el-option label="Mailpit (Desarrollo local)" value="mailpit" />
        <el-option label="AWS SES" value="ses" />
        <el-option label="Mailgun" value="mailgun" />
        <el-option label="Postmark" value="postmark" />
        <el-option label="Resend" value="resend" />
        <el-option label="Sendmail (Local)" value="sendmail" />
        <el-option label="Log (Solo testing)" value="log" />
      </el-select>
      <p class="text-xs text-gray-400 mt-1">Selecciona el proveedor de correo electrónico.</p>
    </div>

    <!-- Campos comunes -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo remitente</label>
        <el-input v-model="form.mail_from_address" placeholder="noreply@example.com" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre remitente</label>
        <el-input v-model="form.mail_from_name" placeholder="Sistema" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Responder a (Reply-To)</label>
        <el-input v-model="form.mail_reply_to_address" type="email" placeholder="referencia@example.com" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de respuesta</label>
        <el-input v-model="form.mail_reply_to_name" placeholder="Referencia" />
      </div>
    </div>
    <p class="text-xs text-gray-400 -mt-2">Los correos de la aplicación llevan este Reply-To con cualquier servicio (SMTP, Resend, SES, Mailgun, Postmark…); las respuestas de las clínicas llegan a esta dirección. Si queda vacío, los correos salen sin Reply-To.</p>

    <!-- Campos SMTP / Mailpit -->
    <template v-if="form.mail_mailer === 'smtp' || form.mail_mailer === 'mailpit'">
      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">{{ form.mail_mailer === 'mailpit' ? 'Configuración Mailpit' : 'Configuración SMTP' }}</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Servidor SMTP</label>
            <el-input v-model="form.mail_host" placeholder="smtp.example.com" />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Puerto</label>
              <el-input-number v-model="form.mail_port" :min="1" :max="65535" controls-position="right" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Encriptación</label>
              <el-select v-model="form.mail_encryption" class="w-full">
                <el-option label="Ninguna" value="" />
                <el-option label="TLS" value="tls" />
                <el-option label="SSL" value="ssl" />
              </el-select>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Usuario</label>
              <el-input v-model="form.mail_username" placeholder="usuario" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Contraseña</label>
              <el-input v-model="form.mail_password" type="password" show-password placeholder="••••••••" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Campos AWS SES -->
    <template v-if="form.mail_mailer === 'ses'">
      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Configuración AWS SES</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Access Key ID</label>
            <el-input v-model="form.ses_key" placeholder="AKIA..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Secret Access Key</label>
            <el-input v-model="form.ses_secret" type="password" show-password placeholder="••••••••" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Región</label>
            <el-select v-model="form.ses_region" filterable class="w-full">
              <el-option label="us-east-1 (Virginia)" value="us-east-1" />
              <el-option label="us-east-2 (Ohio)" value="us-east-2" />
              <el-option label="us-west-1 (California)" value="us-west-1" />
              <el-option label="us-west-2 (Oregon)" value="us-west-2" />
              <el-option label="sa-east-1 (São Paulo)" value="sa-east-1" />
              <el-option label="eu-west-1 (Irlanda)" value="eu-west-1" />
              <el-option label="eu-west-2 (Londres)" value="eu-west-2" />
              <el-option label="eu-central-1 (Frankfurt)" value="eu-central-1" />
            </el-select>
          </div>
        </div>
      </div>
    </template>

    <!-- Campos Mailgun -->
    <template v-if="form.mail_mailer === 'mailgun'">
      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Configuración Mailgun</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Dominio</label>
            <el-input v-model="form.mailgun_domain" placeholder="mg.example.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">API Secret</label>
            <el-input v-model="form.mailgun_secret" type="password" show-password placeholder="key-..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Endpoint</label>
            <el-input v-model="form.mailgun_endpoint" placeholder="api.mailgun.net" />
          </div>
        </div>
      </div>
    </template>

    <!-- Campos Postmark -->
    <template v-if="form.mail_mailer === 'postmark'">
      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Configuración Postmark</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Postmark Token</label>
            <el-input v-model="form.postmark_token" type="password" show-password placeholder="..." />
          </div>
        </div>
      </div>
    </template>

    <!-- Campos Resend -->
    <template v-if="form.mail_mailer === 'resend'">
      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-gray-800 mb-4">Configuración Resend</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
            <el-input v-model="form.resend_key" type="password" show-password placeholder="re_..." />
          </div>
        </div>
      </div>
      <el-alert type="info" :closable="false" show-icon>
        Resend envía por API, no por SMTP. El correo remitente debe pertenecer a un dominio verificado en tu cuenta de Resend.
      </el-alert>
    </template>

    <!-- Info para Mailpit -->
    <template v-if="form.mail_mailer === 'mailpit'">
      <el-alert type="info" :closable="false" show-icon>
        Mailpit es el servidor de correo local de Laragon. Los correos se interceptan localmente para pruebas. Valores por defecto: host 127.0.0.1, puerto 1025, sin encriptación ni autenticación.
      </el-alert>
    </template>

    <!-- Info para log/sendmail -->
    <template v-if="form.mail_mailer === 'log'">
      <el-alert type="info" :closable="false" show-icon>
        Los correos se registrarán en el archivo de log en lugar de enviarse. Útil para desarrollo y pruebas.
      </el-alert>
    </template>

    <template v-if="form.mail_mailer === 'sendmail'">
      <el-alert type="warning" :closable="false" show-icon>
        Sendmail usa el servidor de correo local del sistema. Asegúrate de que esté configurado correctamente en el servidor.
      </el-alert>
    </template>

    <!-- Probar envío -->
    <div class="border-t border-gray-100 pt-4">
      <h4 class="text-sm font-semibold text-gray-800 mb-1.5">Probar envío de correo</h4>
      <p class="text-xs text-gray-400 mb-4">Envía un correo de prueba con la configuración <strong>guardada</strong>. Si cambiaste algo arriba, guarda primero los cambios.</p>
      <div class="flex items-start gap-3">
        <el-input
          v-model="correoPrueba"
          type="email"
          placeholder="correo@destino.com"
          class="flex-1"
          clearable
          @keyup.enter="probarCorreo"
        />
        <el-button
          type="primary"
          :loading="probando"
          :disabled="!correoPrueba.trim()"
          @click="probarCorreo"
        >
          Enviar prueba
        </el-button>
      </div>
      <el-alert
        v-if="resultadoPrueba"
        :type="resultadoPrueba.ok ? 'success' : 'error'"
        :title="resultadoPrueba.ok ? 'Envío exitoso' : 'Falló el envío'"
        :description="resultadoPrueba.mensaje"
        class="mt-4"
        show-icon
        :closable="true"
        @close="resultadoPrueba = null"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import http from '@/plugins/axios';
import notify from '@/plugins/toast';

const settingsStore = useSettingsStore();

const correoPrueba = ref('');
const probando = ref(false);
const resultadoPrueba = ref<{ ok: boolean; mensaje: string } | null>(null);

async function probarCorreo(): Promise<void> {
  const correo = correoPrueba.value.trim();
  if (!correo) return;

  probando.value = true;
  resultadoPrueba.value = null;
  try {
    const { data } = await http.post('/api/settings/probar-correo', { correo });
    resultadoPrueba.value = { ok: true, mensaje: data.message || `Correo enviado a ${correo}` };
    notify.success(data.message || 'Correo de prueba enviado');
  } catch (e: any) {
    const mensaje = e?.response?.data?.message || 'No se pudo enviar el correo de prueba';
    resultadoPrueba.value = { ok: false, mensaje };
    notify.error(mensaje);
  } finally {
    probando.value = false;
  }
}

const form = reactive({
  mail_mailer: 'smtp',
  mail_host: '',
  mail_port: 587,
  mail_encryption: 'tls',
  mail_username: '',
  mail_password: '',
  mail_from_address: '',
  mail_from_name: '',
  mail_reply_to_address: '',
  mail_reply_to_name: '',
  ses_key: '',
  ses_secret: '',
  ses_region: 'us-east-1',
  mailgun_domain: '',
  mailgun_secret: '',
  mailgun_endpoint: 'api.mailgun.net',
  postmark_token: '',
  resend_key: '',
});

function loadForm() {
  form.mail_mailer = settingsStore.getSettingValue('mail', 'mail_mailer', 'smtp');
  form.mail_host = settingsStore.getSettingValue('mail', 'mail_host', '');
  form.mail_port = settingsStore.getSettingValue('mail', 'mail_port', 587);
  form.mail_encryption = settingsStore.getSettingValue('mail', 'mail_encryption', 'tls');
  form.mail_username = settingsStore.getSettingValue('mail', 'mail_username', '');
  form.mail_password = settingsStore.getSettingValue('mail', 'mail_password', '');
  form.mail_from_address = settingsStore.getSettingValue('mail', 'mail_from_address', '');
  form.mail_from_name = settingsStore.getSettingValue('mail', 'mail_from_name', '');
  form.mail_reply_to_address = settingsStore.getSettingValue('mail', 'mail_reply_to_address', '');
  form.mail_reply_to_name = settingsStore.getSettingValue('mail', 'mail_reply_to_name', '');
  form.ses_key = settingsStore.getSettingValue('mail', 'ses_key', '');
  form.ses_secret = settingsStore.getSettingValue('mail', 'ses_secret', '');
  form.ses_region = settingsStore.getSettingValue('mail', 'ses_region', 'us-east-1');
  form.mailgun_domain = settingsStore.getSettingValue('mail', 'mailgun_domain', '');
  form.mailgun_secret = settingsStore.getSettingValue('mail', 'mailgun_secret', '');
  form.mailgun_endpoint = settingsStore.getSettingValue('mail', 'mailgun_endpoint', 'api.mailgun.net');
  form.postmark_token = settingsStore.getSettingValue('mail', 'postmark_token', '');
  form.resend_key = settingsStore.getSettingValue('mail', 'resend_key', '');
}

loadForm();

watch(() => settingsStore.settings, loadForm, { deep: true });

defineExpose({ form, group: 'mail' });
</script>

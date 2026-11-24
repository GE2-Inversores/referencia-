<template>
  <el-dialog
    v-model="showUpdateDialog"
    :show-close="false"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    width="480px"
    align-center
    append-to-body
    class="version-update-dialog"
  >
    <div class="update-content">
      <div class="icon-container">
        <RefreshCwIcon class="update-icon" :size="52" />
      </div>
      <h2 class="update-title">¡Nueva versión disponible!</h2>
      <p class="update-description">
        Hemos lanzado una actualización con mejoras y nuevas funcionalidades.
      </p>
      <div class="version-comparison">
        <div class="version-item current">
          <span class="label">Versión actual</span>
          <span class="version">{{ currentVersion }}</span>
        </div>
        <ArrowRightIcon class="arrow-icon" :size="24" />
        <div class="version-item new">
          <span class="label">Nueva versión</span>
          <span class="version">{{ serverVersion }}</span>
        </div>
      </div>
    </div>
    <template #footer>
      <div class="dialog-footer">
        <el-button type="primary" size="large" class="update-button" @click="reloadPage">
          <RefreshCwIcon :size="16" style="margin-right:6px" />
          Actualizar ahora
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowRight as ArrowRightIcon, RefreshCw as RefreshCwIcon } from '@lucide/vue';
import http from '@/plugins/axios';
// Baseline: la versión con la que se compiló ESTE bundle (quemada por Vite
// en build). Se guarda en localStorage para persistir entre recargas.
import packageInfo from '../../../version.json';

const bundledVersion: string = packageInfo.version || '1.0.0';
const stored = localStorage.getItem('app_version');
const currentVersion = ref(stored && stored !== 'null' && stored !== 'undefined' ? stored : bundledVersion);
const serverVersion = ref(bundledVersion);
const showUpdateDialog = ref(false);
let checkInterval: ReturnType<typeof setInterval> | null = null;

async function checkVersion(): Promise<void> {
  try {
    const { data } = await http.get('/api/version');
    const version: string | undefined = data?.version;

    if (!version || version === 'undefined' || version === 'null') {
      return;
    }

    if (version !== currentVersion.value) {
      serverVersion.value = version;
      showUpdateDialog.value = true;
    }
  } catch {
    // Sin red o servidor caído: se reintenta en el próximo ciclo.
  }
}

function reloadPage(): void {
  try {
    localStorage.setItem('app_version', serverVersion.value);
  } finally {
    window.location.reload();
  }
}

onMounted(() => {
  void checkVersion();
  checkInterval = setInterval(() => { void checkVersion(); }, 60_000);
});

onBeforeUnmount(() => {
  if (checkInterval) {
    clearInterval(checkInterval);
  }
});
</script>

<style scoped>
:deep(.version-update-dialog) {
  border-radius: 16px;
  overflow: hidden;
}
:deep(.version-update-dialog .el-dialog__header) {
  display: none;
}
:deep(.version-update-dialog .el-dialog__body) {
  padding: 32px;
  text-align: center;
  background: linear-gradient(135deg, #144a80 0%, #18569c 100%);
}
.update-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.icon-container {
  width: 110px;
  height: 110px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 22px;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4); }
  50% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(255, 255, 255, 0); }
}
.update-icon { color: white; animation: rotate 3s linear infinite; }
@keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.update-title { font-size: 26px; font-weight: 700; color: white; margin: 0 0 12px 0; }
.update-description { font-size: 15px; color: rgba(255, 255, 255, 0.9); margin: 0 0 26px 0; line-height: 1.5; max-width: 320px; }
.version-comparison {
  display: flex;
  align-items: center;
  gap: 16px;
  background: rgba(255, 255, 255, 0.15);
  padding: 18px 24px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.version-item { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.version-item.current { opacity: 0.7; }
.version-item .label { font-size: 11px; color: rgba(255, 255, 255, 0.8); text-transform: uppercase; letter-spacing: 0.5px; }
.version-item .version { font-size: 19px; font-weight: 700; color: white; }
.version-item.new .version {
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 16px;
  border-radius: 20px;
  border: 2px solid rgba(255, 255, 255, 0.5);
}
.arrow-icon { color: rgba(255, 255, 255, 0.8); }
:deep(.version-update-dialog .el-dialog__footer) { padding: 22px 32px 28px; background: white; text-align: center; }
.dialog-footer { display: flex; justify-content: center; width: 100%; }
.update-button {
  background: linear-gradient(135deg, #144a80 0%, #18569c 100%);
  border: none;
  font-weight: 600;
}
@media (max-width: 768px) {
  :deep(.version-update-dialog) { width: 90% !important; }
  .update-title { font-size: 22px; }
}
</style>

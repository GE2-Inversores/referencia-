<template>
  <el-popover
    placement="bottom-end"
    :width="380"
    trigger="click"
    @show="onOpen"
  >
    <template #reference>
      <button
        class="w-8 h-8 rounded flex items-center justify-center transition-colors relative border-none cursor-pointer"
        :class="light ? 'bg-transparent hover:bg-slate-100 text-slate-500 hover:text-slate-700' : 'bg-transparent hover:bg-white/10 text-[var(--blue-300)] hover:text-white'"
        title="Notificaciones"
      >
        <BellIcon class="w-4.5 h-4.5" />
        <span
          v-if="notifications.hasUnread"
          class="absolute top-[5px] right-[5px] min-w-[16px] h-[16px] px-1 rounded-full bg-red-500 text-[10px] font-bold text-white flex items-center justify-center"
          :class="light ? 'border border-white' : 'border border-[var(--blue-800)]'"
        >
          {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
        </span>
      </button>
    </template>

    <div class="space-y-3">
      <div class="flex items-center justify-between pb-2 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-800">Notificaciones</h3>
        <div class="flex gap-2">
          <el-button
            v-if="notifications.hasUnread"
            text
            size="small"
            @click="notifications.markAllAsRead()"
          >
            Marcar todas
          </el-button>
          <el-button
            v-if="notifications.notifications.length > 0"
            text
            size="small"
            type="danger"
            @click="handleClearAll"
          >
            Limpiar
          </el-button>
        </div>
      </div>

      <div v-loading="notifications.loading" class="max-h-[400px] overflow-y-auto">
        <div v-if="notifications.notifications.length === 0" class="py-8 text-center text-gray-400 text-sm">
          <BellOffIcon class="w-8 h-8 mx-auto mb-2 text-gray-300" />
          No hay notificaciones
        </div>

        <div
          v-for="notification in notifications.notifications"
          :key="notification.id"
          class="flex gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer border-b border-gray-50 last:border-0"
          :class="{ 'bg-blue-50/50': !notification.is_read }"
          @click="handleClick(notification)"
        >
          <div class="shrink-0 mt-0.5">
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center"
              :class="typeColors[notification.type]"
            >
              <component :is="typeIcons[notification.type]" class="w-4 h-4" />
            </div>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <p class="text-[13px] font-semibold text-gray-800 leading-tight">{{ notification.title }}</p>
              <button
                v-if="!notification.is_read"
                class="shrink-0 w-2 h-2 rounded-full bg-blue-500 mt-1.5"
                title="Sin leer"
              />
            </div>
            <p class="text-[12px] text-gray-500 mt-0.5 line-clamp-2">{{ notification.message }}</p>
            <span class="text-[10px] text-gray-400 mt-1 block">{{ formatTime(notification.created_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </el-popover>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { usePolling } from '@/lib/usePolling';
import { ElMessageBox } from 'element-plus';
import { useNotificationsStore, useClinicaNotificationsStore, type Notification } from '@/stores/notifications';
import {
  Bell as BellIcon,
  BellOff as BellOffIcon,
  Info as InfoIcon,
  CheckCircle as CheckCircleIcon,
  AlertTriangle as AlertTriangleIcon,
  XCircle as XCircleIcon,
} from '@lucide/vue';

/**
 * `light`: variante para fondos claros (portal de clínicas externas). Por
 * defecto usa los tonos --blue-* sobre fondo oscuro (header interno).
 * `scope`: qué bandeja de notificaciones consultar — el personal interno y
 * las clínicas externas se autentican distinto y tienen tablas separadas.
 */
const props = withDefaults(defineProps<{ light?: boolean; scope?: 'interno' | 'externo' }>(), { light: false, scope: 'interno' });

const notifications = props.scope === 'externo' ? useClinicaNotificationsStore() : useNotificationsStore();
const router = useRouter();

const typeIcons: Record<string, any> = {
  info: InfoIcon,
  success: CheckCircleIcon,
  warning: AlertTriangleIcon,
  error: XCircleIcon,
};

const typeColors: Record<string, string> = {
  info: 'bg-blue-100 text-blue-600',
  success: 'bg-green-100 text-green-600',
  warning: 'bg-yellow-100 text-yellow-600',
  error: 'bg-red-100 text-red-600',
};

onMounted(() => {
  notifications.fetchUnreadCount();
});

usePolling(() => {
  notifications.fetchUnreadCount();
}, 30000);

function onOpen() {
  notifications.fetchNotifications({ force: true });
}

function handleClick(notification: Notification) {
  if (!notification.is_read) {
    notifications.markAsRead(notification.id);
  }
  if (notification.link) {
    try {
      void router.push(notification.link);
    } catch {
      // Invalid link, ignore
    }
  }
}

async function handleClearAll() {
  try {
    await ElMessageBox.confirm(
      '¿Eliminar todas las notificaciones?',
      'Confirmar',
      { type: 'warning', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' },
    );
    notifications.clearAll();
  } catch {
    // Cancelled
  }
}

function formatTime(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMin = Math.floor(diffMs / 60000);
  const diffHour = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHour / 24);

  if (diffMin < 1) return 'Ahora';
  if (diffMin < 60) return `Hace ${diffMin} min`;
  if (diffHour < 24) return `Hace ${diffHour} h`;
  if (diffDay < 7) return `Hace ${diffDay} d`;
  return date.toLocaleDateString('es-CO');
}
</script>

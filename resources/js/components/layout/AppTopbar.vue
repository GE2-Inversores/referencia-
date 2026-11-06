<template>
  <header
    class="shrink-0 relative z-50 topbar-shell"
    :class="{ 'topbar-shell-dark': layout.isDarkMode }"
  >
    <div class="flex items-center gap-3 h-14 px-4">
      <!-- Menú hamburguesa (solo móvil / tablet) -->
      <button
        type="button"
        class="topbar-action-btn topbar-burger lg:hidden"
        :aria-expanded="menuMovil"
        aria-label="Abrir menú de navegación"
        @click="menuMovil = !menuMovil"
      >
        <component :is="menuMovil ? XIcon : MenuIcon" class="w-5 h-5" />
      </button>

      <!-- Branding -->
      <router-link to="/dashboard" class="flex items-center gap-2.5 shrink-0 no-underline">
        <div class="w-9 h-9 rounded-full flex items-center justify-center overflow-hidden bg-white" style="box-shadow: 0 0 0 2px rgba(255,255,255,0.35);">
          <img :src="logoAvatar" alt="Santa Bárbara" class="w-7 h-7 object-contain" />
        </div>
        <div class="hidden sm:flex flex-col leading-tight">
          <strong class="text-[14px] font-semibold text-white" style="text-shadow: 0 1px 6px rgba(0,0,0,0.25);">Santa Bárbara</strong>
          <span class="text-[9px] font-light tracking-wide" style="color:rgba(255,255,255,0.65);">Clínica de Alta Complejidad</span>
        </div>
      </router-link>

      <div class="topbar-divider hidden lg:block"></div>

      <!-- Navegación desktop -->
      <nav class="hidden lg:flex items-center justify-center gap-1.5 flex-1 min-w-0 overflow-hidden">
        <router-link
          v-for="item in mainItems"
          :key="item.to"
          v-show="!item.permission || can(item.permission)"
          :to="item.to"
          class="topbar-item no-underline"
          active-class="topbar-item-active"
        >
          <component :is="iconOf(item.icon)" class="w-4 h-4 shrink-0" />
          <span>{{ item.text }}</span>
        </router-link>

        <!-- Administración (dropdown) -->
        <el-dropdown v-if="adminItems.some(i => !i.permission || can(i.permission))" trigger="click" placement="bottom-start">
          <button class="topbar-item" :class="{ 'topbar-item-active': isAdminRoute }">
            <component :is="iconOf('Settings')" class="w-4 h-4 shrink-0" />
            <span>Administración</span>
            <ChevronDownIcon class="w-3.5 h-3.5" style="color:rgba(255,255,255,0.55);" />
          </button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item
                v-for="item in adminItems"
                :key="item.to"
                v-show="!item.permission || can(item.permission)"
                @click="router.push(item.to)"
              >
                <component :is="iconOf(item.icon)" class="w-4 h-4 mr-2 inline-block" />
                {{ item.text }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </nav>

      <div class="flex-1 lg:hidden"></div>

      <!-- Acciones -->
      <div class="flex items-center gap-2.5 shrink-0">
        <div class="topbar-actions-group">
          <NotificationCenter :light="false" scope="interno" />
          <span class="topbar-actions-sep"></span>
          <button class="topbar-action-btn" :title="layout.isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'" @click="layout.toggleDarkMode">
            <component :is="layout.isDarkMode ? SunIcon : MoonIcon" class="w-4 h-4" />
          </button>
        </div>
        <span class="topbar-divider hidden md:block" aria-hidden="true"></span>

        <!-- Usuario -->
        <el-dropdown trigger="click" placement="bottom-end" @command="handleCommand">
          <button class="topbar-user">
            <div class="topbar-user-avatar">
              <span class="topbar-user-iniciales">{{ userIniciales }}</span>
            </div>
            <div class="hidden md:flex flex-col leading-tight text-left">
              <span class="topbar-user-name">{{ auth.user?.full_name || 'Usuario' }}</span>
              <span class="topbar-user-role">{{ auth.user?.job_title || 'Personal' }}</span>
            </div>
            <ChevronDownIcon class="w-4 h-4 hidden md:block topbar-user-chevron" />
          </button>
          <template #dropdown>
            <el-dropdown-menu class="w-56">
              <div class="px-4 py-3 bg-gray-50 flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center overflow-hidden">
                  <img :src="logoAvatar" alt="Usuario" class="w-8 h-8 object-contain" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-[13px] font-semibold text-gray-900 truncate">{{ auth.user?.full_name }}</div>
                  <div class="text-[11.5px] text-gray-500 truncate">{{ auth.user?.user_name }}</div>
                </div>
              </div>
              <el-dropdown-item divided command="profile" :icon="UserIcon">Mi perfil</el-dropdown-item>
              <el-dropdown-item command="password" :icon="KeyIcon">Cambiar contrasena</el-dropdown-item>
              <el-dropdown-item divided command="logout" :icon="LogOutIcon" class="!text-red-600 hover:!bg-red-50 hover:!text-red-700">Cerrar sesion</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>

      </div>
    </div>

    <!-- Panel del menú móvil -->
    <Transition name="burger-panel">
      <nav v-if="menuMovil" class="burger-panel lg:hidden" aria-label="Navegación principal">
        <router-link
          v-for="item in mainItems"
          v-show="!item.permission || can(item.permission)"
          :key="item.to"
          :to="item.to"
          class="burger-item no-underline"
          active-class="burger-item-active"
        >
          <span class="burger-item-icon"><component :is="iconOf(item.icon)" class="w-4 h-4" /></span>
          <span>{{ item.text }}</span>
        </router-link>

        <template v-if="adminItems.some(i => !i.permission || can(i.permission))">
          <p class="burger-section">Administración</p>
          <router-link
            v-for="item in adminItems"
            v-show="!item.permission || can(item.permission)"
            :key="item.to"
            :to="item.to"
            class="burger-item no-underline"
            active-class="burger-item-active"
          >
            <span class="burger-item-icon"><component :is="iconOf(item.icon)" class="w-4 h-4" /></span>
            <span>{{ item.text }}</span>
          </router-link>
        </template>
      </nav>
    </Transition>
  </header>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useLayoutStore } from '@/stores/layout';
import { useAuthStore } from '@/stores/auth';
import { usePermission } from '@/composables/usePermission';
import NotificationCenter from './NotificationCenter.vue';
import * as icons from '@lucide/vue';
import {
  ChevronDown as ChevronDownIcon,
  Key as KeyIcon,
  LogOut as LogOutIcon,
  Menu as MenuIcon,
  Moon as MoonIcon,
  X as XIcon,
  Sun as SunIcon,
  User as UserIcon,
} from '@lucide/vue';

interface NavItem {
  to: string;
  icon: string;
  text: string;
  permission?: string;
}

const mainItems: NavItem[] = [
  { to: '/dashboard', icon: 'LayoutDashboard', text: 'Panel principal', permission: 'dashboard.view' },
  { to: '/solicitudes-referencia', icon: 'ClipboardList', text: 'Solicitudes', permission: 'solicitudes.view' },
  { to: '/clinicas', icon: 'Hospital', text: 'Clínicas', permission: 'clinicas.view' },
  { to: '/historico', icon: 'History', text: 'Historial', permission: 'historico.view' },
  { to: '/informes', icon: 'ChartColumn', text: 'Informes', permission: 'dashboard.view' },
];

const adminItems: NavItem[] = [
  { to: '/usuarios', icon: 'Users', text: 'Usuarios', permission: 'users.view' },
  { to: '/roles', icon: 'ShieldCheck', text: 'Roles y permisos', permission: 'roles.view' },
  { to: '/configuracion', icon: 'Settings', text: 'Configuración', permission: 'settings.view' },
];

const layout = useLayoutStore();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const { can } = usePermission();

const logoAvatar = '/images/logo-avatar.png';

const menuMovil = ref(false);
watch(() => route.fullPath, () => { menuMovil.value = false; });

const isAdminRoute = computed(() => adminItems.some(i => route.path.startsWith(i.to)));

function iconOf(name: string) {
  return (icons as any)[name] || icons.Circle;
}

const userIniciales = computed(() =>
  (auth.user?.full_name || 'U').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join(''),
);

function handleCommand(command: string): void {
  if (command === 'logout') {
    void auth.logout();
  } else if (command === 'profile') {
    void router.push({ name: 'profile', query: { tab: 'profile' } });
  } else if (command === 'password') {
    void router.push({ name: 'profile', query: { tab: 'password' } });
  }
}
</script>

<style scoped>
.topbar-shell {
  position: relative;
  background:
    radial-gradient(1200px 90px at 15% -30%, rgba(56,130,246,.35), transparent 60%),
    linear-gradient(95deg, #0b2757 0%, #123a78 45%, #1a56ad 100%);
  box-shadow: 0 3px 16px rgba(8,22,52,.35);
}
.topbar-burger { flex-shrink: 0; margin-left: -4px; }
.burger-panel {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 60;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px 14px;
  border-top: 1px solid rgba(255,255,255,.12);
  background: linear-gradient(180deg, #0f316a, #123a78);
  box-shadow: 0 18px 30px rgba(6,18,44,.4);
}
.topbar-shell-dark .burger-panel { background: linear-gradient(180deg, #111a2e, #0d1322); }
.burger-item {
  display: flex;
  min-height: 44px;
  align-items: center;
  gap: 12px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 12px;
  color: rgba(255,255,255,.86);
  font-size: 13px;
  font-weight: 700;
}
.burger-item-icon {
  display: grid;
  width: 30px;
  height: 30px;
  place-items: center;
  border-radius: 9px;
  background: rgba(255,255,255,.12);
}
.burger-item-active {
  border-color: rgba(255,255,255,.28);
  background: rgba(255,255,255,.16);
  color: #fff;
}
.burger-item-active .burger-item-icon { background: linear-gradient(135deg, #38bdf8, #2563eb); }
.burger-section {
  margin: 8px 4px 2px;
  color: rgba(255,255,255,.5);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.burger-panel-enter-active, .burger-panel-leave-active { transition: opacity .18s ease, transform .18s ease; }
.burger-panel-enter-from, .burger-panel-leave-to { opacity: 0; transform: translateY(-8px); }
.topbar-shell-dark {
  background:
    radial-gradient(1200px 90px at 15% -30%, rgba(56,90,180,.22), transparent 60%),
    linear-gradient(95deg, #0d1322 0%, #121a2e 55%, #15203a 100%);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  box-shadow: none;
}
.topbar-shell::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
  pointer-events: none;
}
.topbar-shell::after {
  content: '';
  position: absolute;
  bottom: -1px; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, #38bdf8, #2563eb, #16468e);
  background-size: 220% 100%;
  animation: topbar-accent 8s linear infinite;
  opacity: 0.9;
  pointer-events: none;
}
@keyframes topbar-accent {
  from { background-position: 0% 0; }
  to { background-position: 220% 0; }
}

.topbar-divider {
  width: 1px;
  height: 26px;
  background: linear-gradient(180deg, transparent, rgba(255,255,255,0.3), transparent);
  flex-shrink: 0;
}

.topbar-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 13px;
  border-radius: 10px;
  border: none;
  background: transparent;
  font-size: 12.5px;
  font-weight: 600;
  color: rgba(255,255,255,0.75);
  cursor: pointer;
  white-space: nowrap;
  transition: background .18s ease, color .18s ease, transform .18s ease;
}
.topbar-item :deep(svg) { opacity: .7; transition: opacity .18s ease; }
.topbar-item:hover {
  background: rgba(255,255,255,0.12);
  color: #fff;
  transform: translateY(-1px);
}
.topbar-item:hover :deep(svg) { opacity: 1; }
.topbar-item-active {
  background: rgba(255,255,255,0.14) !important;
  color: #fff !important;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.14), 0 4px 12px rgba(8,20,48,.2);
}
.topbar-item-active::after {
  content: '';
  position: absolute;
  left: 12px; right: 12px; bottom: 3px;
  height: 2px;
  border-radius: 2px;
  background: linear-gradient(90deg, #7dd3fc, #60a5fa);
}
.topbar-item-active :deep(svg) { opacity: 1; color: #bae6fd; }
.dark .topbar-item-active {
  background: rgba(255,255,255,0.09) !important;
}

/* Acciones agrupadas en vidrio */
.topbar-actions-group {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 3px 6px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.16);
  background: rgba(255,255,255,0.08);
}
.dark .topbar-actions-group {
  border-color: rgba(255,255,255,0.09);
  background: rgba(255,255,255,0.05);
}
.topbar-actions-sep {
  width: 1px;
  height: 16px;
  margin: 0 3px;
  background: rgba(255,255,255,0.18);
}
.topbar-action-btn {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: rgba(255,255,255,0.72);
  cursor: pointer;
  transition: background .15s ease, color .15s ease, transform .15s ease;
}
.topbar-action-btn:hover {
  background: rgba(255,255,255,0.16);
  color: #fff;
  transform: translateY(-1px);
}

/* Usuario sin píldora — solo avatar + texto sobre el navy */
.topbar-user {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 5px 6px;
  margin-left: 2px;
  border: none;
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
  transition: background .15s ease, transform .15s ease;
}
.topbar-user:hover {
  background: rgba(255,255,255,0.1);
}
.topbar-user:active {
  transform: scale(0.98);
}
.topbar-user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #38bdf8, #2563eb 60%, #16468e);
  box-shadow: 0 0 0 2px rgba(255,255,255,0.35), 0 3px 10px rgba(5,15,40,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.dark .topbar-user-avatar {
  box-shadow: 0 0 0 2px rgba(255,255,255,0.18), 0 3px 10px rgba(0,0,0,.5);
}
.topbar-user-iniciales {
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.03em;
}
.topbar-user-chevron { color: rgba(255,255,255,0.6); }
.dark .topbar-user-chevron { color: rgba(255,255,255,0.55); }

.topbar-user-name {
  font-size: 11.5px;
  font-weight: 800;
  color: #fff;
  max-width: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dark .topbar-user-name { color: #fff; }
.topbar-user-role {
  font-size: 9.5px;
  font-weight: 500;
  color: rgba(255,255,255,0.6);
}
.dark .topbar-user-role { color: rgba(255,255,255,0.55); }
</style>

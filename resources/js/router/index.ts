import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useClinicaAuthStore } from '@/stores/clinicaAuth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/ForgotPasswordView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/ResetPasswordView.vue'),
      meta: { isPublic: true },
    },

    // ── Login externo (redirige al login unificado) ──────────────────────────
    {
      path: '/login-externo',
      redirect: '/login',
    },
    {
      path: '/login-externo/verificar-otp',
      name: 'login-externo-otp',
      component: () => import('@/views/login-externo/VerificarOtpView.vue'),
      meta: { isPublic: true },
    },
    {
      path: '/login-externo/magic/:token',
      name: 'login-externo-magic',
      component: () => import('@/views/login-externo/MagicLinkView.vue'),
      meta: { isPublic: true },
    },
    {
      path: '/login-externo/registro',
      name: 'registro-clinica',
      component: () => import('@/views/login-externo/RegistroClinicaView.vue'),
      meta: { isPublic: true },
    },
    // ── Portal público de remisión — sin login, acceso por link directo ──────
    {
      path: '/clinica',
      component: () => import('@/components/layout/ClinicaLayout.vue'),
      meta: { isPublic: true },
      children: [
        {
          path: '',
          redirect: '/clinica/inicio',
        },
        {
          path: 'inicio',
          name: 'clinica-inicio',
          component: () => import('@/views/login-externo/ClinicaDashboardView.vue'),
        },
        {
          path: 'solicitud',
          name: 'clinica-solicitud',
          component: () => import('@/views/login-externo/SolicitudView.vue'),
        },
        {
          path: 'evolucion',
          name: 'clinica-evolucion',
          component: () => import('@/views/login-externo/EvolucionView.vue'),
        },
        {
          // URLs viejas del portal (/clinica/dashboard, /clinica/historial…)
          path: ':pathMatch(.*)*',
          redirect: '/clinica/inicio',
        },
      ],
    },
    {
      path: '/solicitud',
      redirect: '/clinica',
    },
    // ────────────────────────────────────────────────────────────────────────

    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          // El usuario aterriza en el primer módulo que su rol le permite —
          // así un rol sin dashboard no recibe el error de permisos al entrar.
          // Si el usuario aún no está hidratado (carga directa de /), se manda
          // al dashboard y el guard lo reubica después de cargar los permisos.
          redirect: () => {
            const auth = useAuthStore();
            if (!auth.user || auth.hasPermission('dashboard.view')) return '/dashboard';
            if (auth.hasPermission('solicitudes.view')) return '/solicitudes-referencia';
            if (auth.hasPermission('clinicas.view')) return '/clinicas';
            if (auth.hasPermission('historico.view')) return '/historico';
            return '/perfil';
          },
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { permissions: ['dashboard.view'] },
        },
        {
          path: 'informes',
          name: 'informes',
          component: () => import('@/views/informes/InformesView.vue'),
          meta: { permissions: ['dashboard.view'] },
        },
        {
          path: 'reportes',
          name: 'reportes',
          redirect: '/',
          meta: { permissions: ['solicitudes.view'] },
        },
        {
          path: 'historico',
          name: 'historico',
          component: () => import('@/views/historico/HistoricoView.vue'),
          meta: { permissions: ['historico.view'] },
        },
        {
          path: 'usuarios',
          name: 'users',
          component: () => import('@/views/users/UsersView.vue'),
          meta: { permissions: ['users.view'] },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/roles/RolesView.vue'),
          meta: { permissions: ['roles.view'] },
        },
        {
          path: 'configuracion',
          name: 'settings',
          component: () => import('@/views/settings/SettingsView.vue'),
          meta: { permissions: ['settings.view'] },
        },
        {
          path: 'perfil',
          name: 'profile',
          component: () => import('@/views/profile/ProfileView.vue'),
        },
        {
          path: 'clinicas',
          name: 'clinicas',
          component: () => import('@/views/clinicas/ClinicasView.vue'),
          meta: { permissions: ['clinicas.view'] },
        },
        {
          path: 'solicitudes-referencia',
          name: 'solicitudes-referencia',
          component: () => import('@/views/solicitudes/SolicitudesReferenciaView.vue'),
          meta: { permissions: ['solicitudes.view'] },
        },
      ],
    },
    {
      // Cualquier ruta desconocida → raíz (el guard manda a /login si no hay sesión)
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  const clinicaAuth = useClinicaAuthStore();

  const isPublic = to.matched.some((record) => record.meta.isPublic);
  const requiresClinicaAuth = to.matched.some((record) => record.meta.requiresClinicaAuth);
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const requiresGuest = to.matched.some((record) => record.meta.requiresGuest);
  const permissions = to.matched.flatMap((record) => {
    if (!record.meta.permissions) return [];
    return Array.isArray(record.meta.permissions) ? record.meta.permissions : [record.meta.permissions];
  });

  // Rutas totalmente públicas — pasar directo sin hidratar ningún store
  if (isPublic) {
    return true;
  }

  // Guard para rutas de clínica externa
  if (requiresClinicaAuth) {
    const token = clinicaAuth.getToken();
    if (!token) {
      return { name: 'login' };
    }
    const isOriginal = await clinicaAuth.checkDuplicate();
    if (!isOriginal) {
      return { name: 'login' };
    }
    if (!clinicaAuth.isHydrated) {
      await clinicaAuth.fetchClinica();
    }
    if (!clinicaAuth.isAuthenticated) {
      return { name: 'login' };
    }
    return true;
  }

  if (!auth.isHydrated) {
    const isOriginal = await auth.checkDuplicate();
    if (!isOriginal) {
      return { name: 'login' };
    }
    await auth.fetchUser();
  }

  if (requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' };
  }

  if (requiresGuest && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }

  if (permissions.length) {
    const required = permissions as string[];
    const hasAccess = required.some((p: string) => auth.hasPermission(p));
    if (!hasAccess) {
      // Sin toast: el usuario simplemente aterriza en el primer módulo que su
      // rol le permite — mostrar "sin permisos" tras el login solo confunde.
      return auth.hasPermission('dashboard.view')
        ? { name: 'dashboard' }
        : auth.hasPermission('solicitudes.view')
          ? { name: 'solicitudes-referencia' }
          : auth.hasPermission('clinicas.view')
            ? { name: 'clinicas' }
            : auth.hasPermission('historico.view')
              ? { name: 'historico' }
              : { name: 'profile' };
    }
  }

  return true;
});

export default router;

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'
import LandingView from './views/LandingView.vue'
import LoginView from './views/LoginView.vue'
import RegisterView from './views/RegisterView.vue'
import DashboardView from './views/DashboardView.vue'
import AcceptInvitationView from './views/AcceptInvitationView.vue'
import VerifyEmailView from './views/VerifyEmailView.vue'
import ForgotPasswordView from './views/ForgotPasswordView.vue'
import ResetPasswordView from './views/ResetPasswordView.vue'
import PlatformAdminView from './views/PlatformAdminView.vue'
import TeacherWorkspaceView from './views/TeacherWorkspaceView.vue'
import PlatformSettingsView from './views/PlatformSettingsView.vue'
import TeacherAttendanceView from './views/TeacherAttendanceView.vue'
import ParentPortalView from './views/ParentPortalView.vue'

const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: LandingView }, { path: '/login', component: LoginView, meta: { guest: true } },
  { path: '/register', component: RegisterView, meta: { guest: true } },
  { path: '/forgot-password', component: ForgotPasswordView, meta: { guest: true } },
  { path: '/reset-password/:token', component: ResetPasswordView, meta: { guest: true } },
  { path: '/verify-email', component: VerifyEmailView, meta: { verification: true } },
  { path: '/accept-invite', component: AcceptInvitationView },
  { path: '/parent/:token', component: ParentPortalView },
  { path: '/dashboard', component: DashboardView, meta: { auth: true } },
  { path: '/admin/settings/plans', component: PlatformSettingsView, meta: { auth: true, platformAdmin: true } },
  { path: '/admin/:section?', component: PlatformAdminView, meta: { auth: true, platformAdmin: true } },
  { path: '/teacher/attendance', component: TeacherAttendanceView, meta: { auth: true } },
  { path: '/teacher/:section?', component: TeacherWorkspaceView, meta: { auth: true } },
]})
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) await auth.hydrate()
  if (to.meta.verification) {
    if (!auth.user) return '/login'
    if (auth.user.verified) return '/dashboard'
  }
  if (to.meta.auth && !auth.user) return '/login'
  if (to.meta.auth && auth.user && !auth.user.verified) return '/verify-email'
  if (to.meta.platformAdmin && auth.user && !auth.user.is_platform_admin) return '/dashboard'
  if (to.path === '/dashboard' && auth.user?.is_platform_admin) return '/admin/overview'
  if (to.meta.guest && auth.user) return auth.user.verified ? (auth.user.is_platform_admin ? '/admin/overview' : '/dashboard') : '/verify-email'
})
export default router

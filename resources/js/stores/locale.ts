import { defineStore } from 'pinia'
import english from '../../lang/en.json'
import arabic from '../../lang/ar.json'

export type Locale = 'en' | 'ar'
type Catalogue = Record<string, string>

const catalogues: Record<Locale, Catalogue> = { en: english, ar: arabic }
const arabicWords: Record<string, string> = {
  academy: 'الأكاديمية', workspace: 'مساحة العمل', platform: 'المنصة', dashboard: 'لوحة التحكم',
  student: 'الطالب', students: 'الطلاب', teacher: 'المعلم', teachers: 'المعلمون', guardian: 'ولي الأمر',
  attendance: 'الحضور', schedule: 'الجدول', performance: 'الأداء', exam: 'الاختبار', exams: 'الاختبارات',
  marks: 'الدرجات', class: 'الفصل', classes: 'الحصص', group: 'المجموعة', groups: 'المجموعات',
  centre: 'المركز', centres: 'المراكز', center: 'المركز', centers: 'المراكز', year: 'السنة', years: 'السنوات',
  level: 'المستوى', levels: 'المستويات', subject: 'المادة', subjects: 'المواد', status: 'الحالة',
  active: 'نشط', inactive: 'غير نشط', loading: 'جارٍ التحميل', create: 'إنشاء', add: 'إضافة', save: 'حفظ',
  cancel: 'إلغاء', close: 'إغلاق', open: 'فتح', search: 'بحث', settings: 'الإعدادات', users: 'المستخدمون',
  staff: 'الموظفون', role: 'الدور', roles: 'الأدوار', name: 'الاسم', description: 'الوصف', date: 'التاريخ',
  today: 'اليوم', previous: 'السابق', next: 'التالي', all: 'الكل', no: 'لا يوجد', now: 'الآن',
  secure: 'آمن', private: 'خاص', access: 'الوصول', account: 'الحساب', email: 'البريد الإلكتروني',
  phone: 'الهاتف', address: 'العنوان', city: 'المدينة', country: 'الدولة', payment: 'الدفع', payments: 'المدفوعات',
  plan: 'الباقة', plans: 'الباقات', monthly: 'شهري', yearly: 'سنوي', features: 'المزايا',
  welcome: 'مرحبًا', return: 'عودة', back: 'رجوع', submit: 'إرسال', reset: 'إعادة ضبط', apply: 'تطبيق',
  tenant: 'المؤسسة', directory: 'الدليل', global: 'شامل', visibility: 'الرؤية', operations: 'العمليات',
  subscription: 'الاشتراك', identity: 'الهوية', security: 'الأمان', support: 'الدعم', activity: 'النشاط',
  enabled: 'مفعّل', verified: 'موثّق', unverified: 'غير موثّق', organisation: 'المؤسسة',
}

export const useLocaleStore = defineStore('locale', {
  state: () => ({ locale: (localStorage.getItem('academy_locale') === 'ar' ? 'ar' : 'en') as Locale }),
  getters: { isRtl: (state) => state.locale === 'ar' },
  actions: {
    apply() {
      document.documentElement.lang = this.locale
      document.documentElement.dir = this.locale === 'ar' ? 'rtl' : 'ltr'
      localStorage.setItem('academy_locale', this.locale)
    },
    toggle() { this.locale = this.locale === 'en' ? 'ar' : 'en'; this.apply() },
    tr(value: string) {
      const source = value.replace(/\s+/g, ' ').trim()
      return catalogues[this.locale][source] || value
    },
    translateText(value: string) {
      if (this.locale === 'en') return value
      const catalogue = catalogues[this.locale]
      const normalized = value.replace(/\s+/g, ' ')
      const exact = catalogue[normalized.trim()]
      if (exact) return value.replace(normalized.trim(), exact)
      // Translate the longest known phrases first so labels embedded in a sentence
      // are handled without changing names, numbers, or API-provided values.
      const translated = Object.keys(catalogue)
        .filter((key) => key.length > 1 && normalized.includes(key))
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.split(key).join(catalogue[key]), value)
      if (translated !== value) return translated
      return value.replace(/[A-Za-z]+/g, (word) => arabicWords[word.toLowerCase()] || word)
    },
  },
})

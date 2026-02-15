// Import CSS
import '../scss/style.scss';

// Import Sentry
import { initSentry } from './utils/sentry';

// Initialize Sentry
initSentry();

// // Swiper
// import Swiper from 'swiper';
// import { Navigation as SwiperNavigation, Pagination as SwiperPagination, Autoplay } from 'swiper/modules';
// import 'swiper/css';
// import 'swiper/css/navigation';
// import 'swiper/css/pagination';

// // Stelle Swiper global zur Verfügung
// window.Swiper = Swiper;
// window.SwiperModules = { 
//   Navigation: SwiperNavigation, 
//   Pagination: SwiperPagination, 
//   Autoplay 
// };

// // Swiper komplett aus node_modules
// import Swiper from 'swiper/bundle';
// import 'swiper/css/bundle';

// // Global verfügbar machen
// window.Swiper = Swiper;

// Components
import HeroSlider from './components/hero-slider';
import Accordion from './components/accordion';
import './components/carousel';
import BackToTop from './components/back-to-top';
import CookieNotice from './components/cookie-notice';
import './components/faq-accordion';
import DarkMode from './components/theme-switcher';
import ImageComparison from './components/image-comparison';
import Lightbox from './components/lightbox';
import LogoCarousel from './components/logo-carousel';
import Modal from './components/modal';
import Navigation from './components/navigation';
import Notifications from './components/notifications';
import ScrollAnimations from './components/scroll-animations';
import Spoiler from './components/spoiler';
import StatsCounter from './components/stats-counter';
import Tabs from './components/tabs';
import TestimonialsSlider from './components/testimonials-slider';
import VideoPlayer from './components/video-player';
import './components/ajax-search';
import './components/load-more';
import './components/google-maps';
import AjaxFilters from './components/ajax-filters.js';

// Theme loaded
console.log('✨ Custom Theme loaded with Vite + Autoprefixer');
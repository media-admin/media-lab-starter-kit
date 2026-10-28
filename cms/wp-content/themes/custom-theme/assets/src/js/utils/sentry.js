/**
 * Sentry Browser Integration
 */

import * as Sentry from "@sentry/browser";

// true, sobald Sentry.init() gelaufen ist. Ohne DSN bleibt es false und es wird nichts gemeldet.
let sentryActive = false;

export function initSentry() {
  // Ohne konfigurierte DSN läuft Sentry nicht. Die DSN kommt beim Build aus VITE_SENTRY_DSN,
  // zum Beispiel: VITE_SENTRY_DSN=https://… npm run build
  const dsn = import.meta.env.VITE_SENTRY_DSN;
  if (!dsn) {
    return;
  }

  // Don't initialize in development
  if (window.location.hostname === 'localhost' || 
      window.location.hostname.includes('.localdev')) {
    return;
  }
  
  Sentry.init({
    dsn,
    environment: getSentryEnvironment(),
    release: import.meta.env.VITE_SENTRY_RELEASE || undefined, // optional, soll zum Backend passen
    
    // Performance Monitoring
    integrations: [
      Sentry.browserTracingIntegration({
        tracePropagationTargets: [
          "localhost",
          /^\//,
          window.location.hostname
        ],
      }),
    ],
    
    tracesSampleRate: 0.2,
    
    // Ignore common errors
    ignoreErrors: [
      'ResizeObserver loop limit exceeded',
      'Non-Error promise rejection captured',
    ],
    
    // Add custom context
    beforeSend(event, hint) {
      // Add WordPress context
      if (window.wp) {
        event.contexts = event.contexts || {};
        event.contexts.wordpress = {
          ajaxUrl: window.customTheme?.ajaxUrl,
          themePath: window.customTheme?.themePath,
        };
      }
      
      return event;
    },
  });

  sentryActive = true;
}

function getSentryEnvironment() {
  const hostname = window.location.hostname;
  
  if (hostname.includes('staging')) {
    return 'staging';
  } else if (hostname.includes('.localdev') || hostname === 'localhost') {
    return 'development';
  } else {
    return 'production';
  }
}

// Catch unhandled promise rejections
window.addEventListener('unhandledrejection', event => {
  if (sentryActive) {
    Sentry.captureException(event.reason);
  }
});
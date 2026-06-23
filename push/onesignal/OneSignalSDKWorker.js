// Service worker do OneSignal, isolado em /push/onesignal/ pra não conflitar
// com o service worker do PWA (/sw.js, escopo "/").
importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");

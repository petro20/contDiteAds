// Service worker ÚNICO da raiz (escopo "/").
// O OneSignal v16 sempre procura o worker em /OneSignalSDKWorker.js — então
// ele fica aqui. Pra não ter dois service workers brigando pelo escopo "/",
// este mesmo arquivo também carrega o cache/PWA do app (sw.js).
importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");
importScripts("/sw.js");

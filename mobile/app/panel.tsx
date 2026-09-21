import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, BackHandler, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { WebView } from 'react-native-webview';
import * as Notifications from 'expo-notifications';
import * as WebBrowser from 'expo-web-browser';
import * as SecureStore from 'expo-secure-store';
import { getToken, getUser } from '@/auth';
import { API_BASE_URL } from '@/config';

/** Ruta del panel a la que lleva una notificación, según su data. */
function pathForNotification(data: Record<string, unknown>, slug: string): string | null {
  if (!slug) return null;
  if (data.conversation_id) return `/admin/${slug}/conversaciones?c=${data.conversation_id}`;
  if (data.pedido_id) return `/admin/${slug}/despacho`;
  return null;
}

/**
 * Panel completo dentro de un WebView: misma funcionalidad que la web
 * (POS, caja, despacho, inventario, ventas, conversaciones, onboarding…).
 * Entra ya autenticado vía /app-login. El push es nativo, encima del WebView.
 */
export default function Panel() {
  const [uri, setUri] = useState<string | null>(null);
  const [slug, setSlug] = useState<string>('');
  const [canGoBack, setCanGoBack] = useState(false);
  const webRef = useRef<WebView>(null);

  useEffect(() => {
    (async () => {
      const token = await getToken();
      if (!token) {
        router.replace('/login');
        return;
      }
      const user = await getUser();
      const s = user?.negocioSlug ?? '';
      setSlug(s);

      // Arranque en frío: si la app se abrió tocando una notificación, entrar
      // directo a esa pantalla (el listener no captura la que lanzó la app).
      let next = '';
      try {
        const last = await Notifications.getLastNotificationResponseAsync();
        const id = last?.notification.request.identifier ?? '';
        const handled = await SecureStore.getItemAsync('lastNotifHandled');
        if (id && id !== handled) {
          const data = (last!.notification.request.content.data ?? {}) as Record<string, unknown>;
          const p = pathForNotification(data, s);
          if (p) {
            next = `&next=${encodeURIComponent(p)}`;
            await SecureStore.setItemAsync('lastNotifHandled', id);
          }
        }
      } catch {
        // Si algo falla, entramos al panel normal.
      }

      setUri(`${API_BASE_URL}/sesion-movil?token=${encodeURIComponent(token)}${next}`);
    })();
  }, []);

  // App en segundo plano: al tocar la notificación, navega el WebView.
  useEffect(() => {
    const sub = Notifications.addNotificationResponseReceivedListener((resp) => {
      const data = (resp.notification.request.content.data ?? {}) as Record<string, unknown>;
      const path = pathForNotification(data, slug);
      if (path) {
        webRef.current?.injectJavaScript(`window.location.href=${JSON.stringify(API_BASE_URL + path)};true;`);
      }
    });
    return () => sub.remove();
  }, [slug]);

  // Android: el botón atrás navega dentro del panel.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (canGoBack) {
        webRef.current?.goBack();
        return true;
      }
      return false;
    });
    return () => sub.remove();
  }, [canGoBack]);

  if (!uri) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#22d3ee" />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: '#06272e' }}>
      <WebView
        ref={webRef}
        source={{ uri }}
        onNavigationStateChange={(s) => setCanGoBack(s.canGoBack)}
        // El login de Facebook (Embedded Signup) no funciona dentro de un WebView:
        // lo abrimos en el navegador del sistema.
        onShouldStartLoadWithRequest={(req) => {
          if (/facebook\.com|fb\.com/i.test(req.url)) {
            WebBrowser.openBrowserAsync(req.url);
            return false;
          }
          return true;
        }}
        startInLoadingState
        renderLoading={() => (
          <View style={styles.center}>
            <ActivityIndicator size="large" color="#22d3ee" />
          </View>
        )}
        style={{ flex: 1 }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#06272e' },
});

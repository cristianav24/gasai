import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, BackHandler, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { WebView } from 'react-native-webview';
import * as Notifications from 'expo-notifications';
import * as WebBrowser from 'expo-web-browser';
import { getToken, getUser } from '@/auth';
import { API_BASE_URL } from '@/config';

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
      setSlug(user?.negocioSlug ?? '');
      setUri(`${API_BASE_URL}/app-login?token=${encodeURIComponent(token)}`);
    })();
  }, []);

  // Al tocar una notificación, lleva el panel a la pantalla correspondiente.
  useEffect(() => {
    const sub = Notifications.addNotificationResponseReceivedListener((resp) => {
      const data = (resp.notification.request.content.data ?? {}) as Record<string, unknown>;
      if (!slug) return;
      let path: string | null = null;
      if (data.conversation_id) path = `/admin/${slug}/conversaciones`;
      else if (data.pedido_id) path = `/admin/${slug}/despacho`;
      if (path) {
        webRef.current?.injectJavaScript(`window.location.href='${API_BASE_URL}${path}';true;`);
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

import { useEffect } from 'react';
import { Stack, router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import * as Notifications from 'expo-notifications';

// Mostrar las notificaciones también con la app abierta.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

export default function RootLayout() {
  // Al tocar una notificación, abre la conversación o el pedido correspondiente.
  useEffect(() => {
    const sub = Notifications.addNotificationResponseReceivedListener((resp) => {
      const data = (resp.notification.request.content.data ?? {}) as Record<string, unknown>;
      if (data.conversation_id) {
        router.push(`/conversations/${data.conversation_id}`);
      } else if (data.pedido_id) {
        router.push(`/orders/${data.pedido_id}`);
      }
    });
    return () => sub.remove();
  }, []);

  return (
    <>
      <StatusBar style="dark" />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: '#f59e0b' },
          headerTintColor: '#111',
          headerTitleStyle: { fontWeight: '700' },
        }}
      >
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ title: 'Iniciar sesión' }} />
        <Stack.Screen name="conversations/index" options={{ title: 'Conversaciones' }} />
        <Stack.Screen name="conversations/[id]" options={{ title: 'Chat' }} />
        <Stack.Screen name="orders/index" options={{ title: 'Pedidos' }} />
        <Stack.Screen name="orders/[id]" options={{ title: 'Pedido' }} />
      </Stack>
    </>
  );
}

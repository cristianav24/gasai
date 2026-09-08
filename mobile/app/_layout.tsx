import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

export default function RootLayout() {
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
        <Stack.Screen name="orders/index" options={{ title: 'Mis pedidos' }} />
        <Stack.Screen name="orders/[id]" options={{ title: 'Pedido' }} />
      </Stack>
    </>
  );
}

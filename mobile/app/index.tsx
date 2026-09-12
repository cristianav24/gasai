import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { router } from 'expo-router';
import { getToken, getUser } from '@/auth';

/** Pantalla de arranque: decide a dónde ir según sesión y rol. */
export default function Index() {
  useEffect(() => {
    (async () => {
      const token = await getToken();
      if (!token) {
        router.replace('/login');
        return;
      }
      const user = await getUser();
      // Repartidor: pedidos. Dueño/operador: conversaciones.
      router.replace(user?.rol === 'courier' ? '/orders' : '/conversations');
    })();
  }, []);

  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <ActivityIndicator size="large" color="#f59e0b" />
    </View>
  );
}

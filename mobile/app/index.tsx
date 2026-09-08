import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { router } from 'expo-router';
import { getToken } from '@/auth';

/** Pantalla de arranque: decide a dónde ir según haya sesión o no. */
export default function Index() {
  useEffect(() => {
    (async () => {
      const token = await getToken();
      router.replace(token ? '/orders' : '/login');
    })();
  }, []);

  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <ActivityIndicator size="large" color="#f59e0b" />
    </View>
  );
}

import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { router } from 'expo-router';
import { getToken } from '@/auth';

export default function Index() {
  useEffect(() => {
    (async () => {
      const token = await getToken();
      router.replace(token ? '/panel' : '/login');
    })();
  }, []);

  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#06272e' }}>
      <ActivityIndicator size="large" color="#22d3ee" />
    </View>
  );
}

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { fetchConversations, logout, ConversationSummary } from '@/api';

export default function ConversationsList() {
  const [items, setItems] = useState<ConversationSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setItems(await fetchConversations());
    } catch (e: any) {
      setError(e?.message ?? 'No se pudieron cargar las conversaciones.');
      if (e?.status === 401) router.replace('/login');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load();
    }, [load])
  );

  async function onLogout() {
    await logout();
    router.replace('/login');
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={items}
        keyExtractor={(c) => String(c.id)}
        contentContainerStyle={{ padding: 16 }}
        refreshControl={<RefreshControl refreshing={false} onRefresh={load} />}
        ListEmptyComponent={
          <View style={styles.center}>
            <Text style={styles.empty}>{error ?? 'Aún no hay conversaciones.'}</Text>
          </View>
        }
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.card} onPress={() => router.push(`/conversations/${item.id}`)}>
            <View style={styles.row}>
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{item.contacto.slice(0, 2).toUpperCase()}</Text>
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <View style={styles.top}>
                  <Text style={styles.name} numberOfLines={1}>{item.contacto}</Text>
                  <View style={[styles.dot, { backgroundColor: item.estado === 'humano' ? '#f59e0b' : '#22c55e' }]} />
                </View>
                <Text style={styles.preview} numberOfLines={1}>
                  {item.ultimo_mensaje ?? 'Sin mensajes'}
                </Text>
              </View>
            </View>
          </TouchableOpacity>
        )}
      />

      <View style={styles.footer}>
        <TouchableOpacity onPress={() => router.push('/orders')}>
          <Text style={styles.link}>Ver pedidos</Text>
        </TouchableOpacity>
        <TouchableOpacity onPress={onLogout}>
          <Text style={styles.logout}>Cerrar sesión</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  empty: { color: '#6b7280', textAlign: 'center' },
  card: { backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 10, borderWidth: 1, borderColor: '#eee' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 42, height: 42, borderRadius: 21, backgroundColor: '#fde68a', alignItems: 'center', justifyContent: 'center' },
  avatarText: { fontWeight: '800', color: '#92400e', fontSize: 13 },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  name: { fontSize: 15, fontWeight: '700', color: '#111', flex: 1 },
  dot: { width: 9, height: 9, borderRadius: 5, marginLeft: 8 },
  preview: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  footer: { flexDirection: 'row', justifyContent: 'space-between', padding: 16 },
  link: { color: '#0891a6', fontWeight: '700' },
  logout: { color: '#dc2626', fontWeight: '600' },
});

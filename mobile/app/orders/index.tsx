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
import { fetchOrders, logout, Order } from '@/api';

export default function OrdersList() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setOrders(await fetchOrders());
    } catch (e: any) {
      setError(e?.message ?? 'No se pudieron cargar los pedidos.');
      if (e?.status === 401) router.replace('/login');
    } finally {
      setLoading(false);
    }
  }, []);

  // Recarga cada vez que la pantalla toma foco (p. ej. al volver del detalle).
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
        data={orders}
        keyExtractor={(o) => String(o.id)}
        contentContainerStyle={{ padding: 16 }}
        refreshControl={<RefreshControl refreshing={false} onRefresh={load} />}
        ListEmptyComponent={
          <View style={styles.center}>
            <Text style={styles.empty}>
              {error ?? 'No tienes pedidos asignados por ahora.'}
            </Text>
          </View>
        }
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.card} onPress={() => router.push(`/orders/${item.id}`)}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardId}>#{item.id}</Text>
              <View style={[styles.badge, badgeStyle(item.estado)]}>
                <Text style={styles.badgeText}>{item.estado_label}</Text>
              </View>
            </View>
            <Text style={styles.cardCustomer}>
              {item.cliente.nombre ?? item.cliente.telefono ?? 'Sin cliente'}
            </Text>
            <Text style={styles.cardMeta}>
              {item.direccion.texto ?? 'Sin dirección'}
            </Text>
            <Text style={styles.cardMeta}>
              {item.fecha_programada ?? ''} · {item.franja ?? ''} · S/ {item.total.toFixed(2)}
            </Text>
          </TouchableOpacity>
        )}
      />

      <TouchableOpacity style={styles.logout} onPress={onLogout}>
        <Text style={styles.logoutText}>Cerrar sesión</Text>
      </TouchableOpacity>
    </View>
  );
}

function badgeStyle(estado: string) {
  return { backgroundColor: estado === 'en_ruta' ? '#dbeafe' : '#dcfce7' };
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  empty: { color: '#6b7280', textAlign: 'center' },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#eee',
  },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  cardId: { fontSize: 16, fontWeight: '800', color: '#111' },
  badge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 2 },
  badgeText: { fontSize: 12, fontWeight: '600', color: '#111' },
  cardCustomer: { fontSize: 15, fontWeight: '600', marginTop: 6, color: '#111' },
  cardMeta: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  logout: { padding: 16, alignItems: 'center' },
  logoutText: { color: '#dc2626', fontWeight: '600' },
});

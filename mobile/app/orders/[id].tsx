import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { fetchOrder, markDelivered, Order } from '@/api';

export default function OrderDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const orderId = Number(id);

  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setOrder(await fetchOrder(orderId));
    } catch (e: any) {
      Alert.alert('Error', e?.message ?? 'No se pudo cargar el pedido.');
      if (e?.status === 401) router.replace('/login');
    } finally {
      setLoading(false);
    }
  }, [orderId]);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load();
    }, [load])
  );

  function confirmDelivered() {
    Alert.alert('Confirmar entrega', '¿Marcar este pedido como entregado?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Sí, entregado', onPress: onDelivered },
    ]);
  }

  async function onDelivered() {
    setSaving(true);
    try {
      await markDelivered(orderId);
      Alert.alert('¡Listo!', 'Pedido marcado como entregado.');
      router.replace('/orders');
    } catch (e: any) {
      Alert.alert('Error', e?.message ?? 'No se pudo actualizar.');
    } finally {
      setSaving(false);
    }
  }

  function openMap() {
    if (order?.direccion.lat && order?.direccion.lng) {
      Linking.openURL(`https://maps.google.com/?q=${order.direccion.lat},${order.direccion.lng}`);
    } else if (order?.direccion.texto) {
      Linking.openURL(`https://maps.google.com/?q=${encodeURIComponent(order.direccion.texto)}`);
    }
  }

  if (loading || !order) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  const entregado = order.estado === 'entregado';

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
      <Text style={styles.title}>Pedido #{order.id}</Text>
      <Text style={styles.badge}>{order.estado_label}</Text>

      <Section label="Cliente">
        <Text style={styles.value}>{order.cliente.nombre ?? '—'}</Text>
        {order.cliente.telefono ? (
          <TouchableOpacity onPress={() => Linking.openURL(`tel:${order.cliente.telefono}`)}>
            <Text style={styles.link}>{order.cliente.telefono}</Text>
          </TouchableOpacity>
        ) : null}
      </Section>

      <Section label="Entrega">
        <Text style={styles.value}>{order.direccion.texto ?? 'Sin dirección'}</Text>
        {order.direccion.referencia ? (
          <Text style={styles.meta}>Ref: {order.direccion.referencia}</Text>
        ) : null}
        <Text style={styles.meta}>
          {order.fecha_programada ?? ''} · {order.franja ?? ''}
          {order.hora ? ` · ${order.hora}` : ''}
        </Text>
        <TouchableOpacity onPress={openMap}>
          <Text style={styles.link}>Abrir en el mapa</Text>
        </TouchableOpacity>
      </Section>

      <Section label="Productos">
        {order.items.map((it, i) => (
          <Text key={i} style={styles.value}>
            {it.cantidad} × {it.producto}
          </Text>
        ))}
        <Text style={styles.total}>Total: S/ {order.total.toFixed(2)}</Text>
      </Section>

      {order.notas ? (
        <Section label="Notas">
          <Text style={styles.value}>{order.notas}</Text>
        </Section>
      ) : null}

      {!entregado ? (
        <TouchableOpacity style={styles.button} onPress={confirmDelivered} disabled={saving}>
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Marcar como entregado</Text>
          )}
        </TouchableOpacity>
      ) : (
        <Text style={styles.doneText}>✓ Entregado</Text>
      )}
    </ScrollView>
  );
}

function Section({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionLabel}>{label}</Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '800', color: '#111' },
  badge: { color: '#6b7280', marginTop: 2, marginBottom: 8 },
  section: { marginTop: 20 },
  sectionLabel: { fontSize: 13, fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase' },
  value: { fontSize: 16, color: '#111', marginTop: 4 },
  meta: { fontSize: 14, color: '#6b7280', marginTop: 2 },
  link: { fontSize: 15, color: '#d97706', marginTop: 6, fontWeight: '600' },
  total: { fontSize: 16, fontWeight: '700', marginTop: 8, color: '#111' },
  button: {
    backgroundColor: '#16a34a',
    borderRadius: 10,
    padding: 16,
    alignItems: 'center',
    marginTop: 28,
    marginBottom: 40,
  },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  doneText: { color: '#16a34a', fontSize: 18, fontWeight: '700', textAlign: 'center', marginTop: 28 },
});

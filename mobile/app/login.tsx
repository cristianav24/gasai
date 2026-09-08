import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { login } from '@/api';
import { registerForPush } from '@/push';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  async function onSubmit() {
    if (!email || !password) {
      Alert.alert('Faltan datos', 'Ingresa tu correo y contraseña.');
      return;
    }

    setLoading(true);
    try {
      await login(email.trim(), password);
      await registerForPush(); // Registra el dispositivo para recibir avisos.
      router.replace('/orders');
    } catch (e: any) {
      Alert.alert('No se pudo entrar', e?.message ?? 'Revisa tus credenciales.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.container}
    >
      <Text style={styles.title}>GasAI Repartidor</Text>
      <Text style={styles.subtitle}>Entra para ver tus pedidos del día.</Text>

      <TextInput
        style={styles.input}
        placeholder="Correo"
        autoCapitalize="none"
        keyboardType="email-address"
        value={email}
        onChangeText={setEmail}
      />
      <TextInput
        style={styles.input}
        placeholder="Contraseña"
        secureTextEntry
        value={password}
        onChangeText={setPassword}
      />

      <TouchableOpacity style={styles.button} onPress={onSubmit} disabled={loading}>
        {loading ? (
          <ActivityIndicator color="#111" />
        ) : (
          <Text style={styles.buttonText}>Entrar</Text>
        )}
      </TouchableOpacity>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', padding: 24, backgroundColor: '#fff' },
  title: { fontSize: 28, fontWeight: '800', color: '#111' },
  subtitle: { fontSize: 15, color: '#6b7280', marginTop: 4, marginBottom: 24 },
  input: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    padding: 14,
    fontSize: 16,
    marginBottom: 12,
  },
  button: {
    backgroundColor: '#f59e0b',
    borderRadius: 10,
    padding: 16,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonText: { color: '#111', fontSize: 16, fontWeight: '700' },
});

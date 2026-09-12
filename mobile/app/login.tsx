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
      await registerForPush();
      router.replace('/panel');
    } catch (e: any) {
      Alert.alert('No se pudo entrar', e?.message ?? 'Revisa tus credenciales.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.screen}
    >
      <View style={styles.brand}>
        <View style={styles.mark}><Text style={styles.markDrop}>💧</Text></View>
        <Text style={styles.title}>GasAI</Text>
        <Text style={styles.subtitle}>Tu reparto de agua y gas, en tu bolsillo.</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>Correo</Text>
        <TextInput
          style={styles.input}
          placeholder="tucorreo@ejemplo.com"
          placeholderTextColor="#9fb3b8"
          autoCapitalize="none"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
        />
        <Text style={styles.label}>Contraseña</Text>
        <TextInput
          style={styles.input}
          placeholder="••••••••"
          placeholderTextColor="#9fb3b8"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
        />

        <TouchableOpacity style={styles.button} onPress={onSubmit} disabled={loading}>
          {loading ? <ActivityIndicator color="#1c1917" /> : <Text style={styles.buttonText}>Entrar</Text>}
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#06272e', justifyContent: 'center', padding: 24 },
  brand: { alignItems: 'center', marginBottom: 28 },
  mark: { width: 64, height: 64, borderRadius: 18, backgroundColor: '#0e5561', alignItems: 'center', justifyContent: 'center' },
  markDrop: { fontSize: 32 },
  title: { fontSize: 34, fontWeight: '800', color: '#fff', marginTop: 14, letterSpacing: -0.5 },
  subtitle: { fontSize: 15, color: '#9fc6cd', marginTop: 4, textAlign: 'center' },
  card: { backgroundColor: '#fff', borderRadius: 20, padding: 20 },
  label: { fontSize: 13, fontWeight: '700', color: '#0b4a57', marginBottom: 6, marginTop: 6 },
  input: { borderWidth: 1, borderColor: '#dbe7ea', borderRadius: 12, padding: 14, fontSize: 16, color: '#06272e', marginBottom: 6 },
  button: { backgroundColor: '#f59e0b', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 14 },
  buttonText: { color: '#1c1917', fontSize: 16, fontWeight: '800' },
});

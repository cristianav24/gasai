import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { fetchConversation, replyConversation, ChatMessage } from '@/api';

export default function ConversationThreadScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const convId = Number(id);

  const [mensajes, setMensajes] = useState<ChatMessage[]>([]);
  const [contacto, setContacto] = useState('Chat');
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const listRef = useRef<FlatList<ChatMessage>>(null);

  const load = useCallback(async () => {
    try {
      const data = await fetchConversation(convId);
      setContacto(data.contacto);
      setMensajes(data.mensajes);
    } catch (e: any) {
      if (e?.status === 401) router.replace('/login');
    } finally {
      setLoading(false);
    }
  }, [convId]);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load();
    }, [load])
  );

  async function onSend() {
    const texto = draft.trim();
    if (!texto || sending) return;
    setSending(true);
    setDraft('');
    // Optimista: mostramos el mensaje al instante.
    setMensajes((prev) => [...prev, { rol: 'assistant', contenido: texto, hora: null }]);
    try {
      const res = await replyConversation(convId, texto);
      if (res.aviso) Alert.alert('Aviso', res.aviso);
    } catch (e: any) {
      Alert.alert('No se pudo enviar', e?.message ?? 'Intenta de nuevo.');
      load();
    } finally {
      setSending(false);
    }
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={90}
    >
      <FlatList
        ref={listRef}
        data={mensajes}
        keyExtractor={(_, i) => String(i)}
        contentContainerStyle={{ padding: 14, gap: 8 }}
        onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
        renderItem={({ item }) => (
          <View style={[styles.bubble, item.rol === 'user' ? styles.in : styles.out]}>
            <Text style={item.rol === 'user' ? styles.inText : styles.outText}>{item.contenido}</Text>
          </View>
        )}
        ListEmptyComponent={
          <View style={styles.center}><Text style={styles.empty}>Sin mensajes aún.</Text></View>
        }
      />

      <View style={styles.composer}>
        <TextInput
          style={styles.input}
          placeholder={`Responder a ${contacto}…`}
          value={draft}
          onChangeText={setDraft}
          multiline
        />
        <TouchableOpacity style={styles.send} onPress={onSend} disabled={sending}>
          <Text style={styles.sendText}>{sending ? '…' : 'Enviar'}</Text>
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#eef2f4' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  empty: { color: '#6b7280' },
  bubble: { maxWidth: '82%', padding: 10, borderRadius: 14 },
  in: { alignSelf: 'flex-start', backgroundColor: '#fff', borderBottomLeftRadius: 4 },
  out: { alignSelf: 'flex-end', backgroundColor: '#f59e0b', borderBottomRightRadius: 4 },
  inText: { color: '#111', fontSize: 15 },
  outText: { color: '#1c1917', fontSize: 15 },
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: 8, padding: 10, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#e5e7eb' },
  input: { flex: 1, maxHeight: 120, borderWidth: 1, borderColor: '#e5e7eb', borderRadius: 20, paddingHorizontal: 14, paddingVertical: 8, fontSize: 15 },
  send: { backgroundColor: '#f59e0b', borderRadius: 20, paddingHorizontal: 18, paddingVertical: 10 },
  sendText: { color: '#1c1917', fontWeight: '700' },
});

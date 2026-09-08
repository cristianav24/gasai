import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'gasai_token';
const USER_KEY = 'gasai_user';

export type SessionUser = {
  id: number;
  nombre: string;
  rol: string | null;
  negocio: string;
};

/** Guarda el token y los datos del usuario tras el login. */
export async function saveSession(token: string, user: SessionUser): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
}

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function getUser(): Promise<SessionUser | null> {
  const raw = await SecureStore.getItemAsync(USER_KEY);
  return raw ? (JSON.parse(raw) as SessionUser) : null;
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
  await SecureStore.deleteItemAsync(USER_KEY);
}

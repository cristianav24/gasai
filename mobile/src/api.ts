import { API_BASE_URL } from './config';
import { clearSession, getToken, saveSession, SessionUser } from './auth';

export type OrderItem = { producto: string; cantidad: number };

export type Order = {
  id: number;
  estado: string;
  estado_label: string;
  total: number;
  fecha_programada: string | null;
  franja: string | null;
  hora: string | null;
  notas: string | null;
  cliente: { nombre: string | null; telefono: string | null };
  direccion: {
    texto: string | null;
    referencia: string | null;
    lat: number | null;
    lng: number | null;
  };
  items: OrderItem[];
};

class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = await getToken();

  const res = await fetch(`${API_BASE_URL}/api${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers ?? {}),
    },
  });

  if (res.status === 401) {
    await clearSession();
    throw new ApiError(401, 'Sesión expirada. Vuelve a entrar.');
  }

  const data = res.status === 204 ? null : await res.json().catch(() => null);

  if (!res.ok) {
    const message =
      (data && (data.message as string)) || 'Ocurrió un error. Intenta de nuevo.';
    throw new ApiError(res.status, message);
  }

  return data as T;
}

/** Login del repartidor. Guarda la sesión y devuelve el usuario. */
export async function login(email: string, password: string): Promise<SessionUser> {
  const res = await request<{
    token: string;
    usuario: { id: number; nombre: string; rol: string | null };
    negocio: { id: number; nombre: string; slug: string };
  }>('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password, device_name: 'app-repartidor' }),
  });

  const user: SessionUser = {
    id: res.usuario.id,
    nombre: res.usuario.nombre,
    rol: res.usuario.rol,
    negocio: res.negocio.nombre,
  };

  await saveSession(res.token, user);
  return user;
}

export async function logout(): Promise<void> {
  try {
    await request('/logout', { method: 'POST' });
  } finally {
    await clearSession();
  }
}

export async function fetchOrders(): Promise<Order[]> {
  const res = await request<{ data: Order[] }>('/orders');
  return res.data;
}

export async function fetchOrder(id: number): Promise<Order> {
  const res = await request<{ data: Order }>(`/orders/${id}`);
  return res.data;
}

export async function markDelivered(id: number): Promise<Order> {
  const res = await request<{ data: Order }>(`/orders/${id}/delivered`, {
    method: 'POST',
  });
  return res.data;
}

export type ConversationSummary = {
  id: number;
  contacto: string;
  telefono: string | null;
  estado: string;
  ultimo_mensaje: string | null;
  actualizado: string | null;
};

export type ChatMessage = { rol: 'user' | 'assistant'; contenido: string; hora: string | null };

export type ConversationThread = {
  id: number;
  contacto: string;
  estado: string;
  mensajes: ChatMessage[];
};

export async function fetchConversations(): Promise<ConversationSummary[]> {
  const res = await request<{ data: ConversationSummary[] }>('/conversations');
  return res.data;
}

export async function fetchConversation(id: number): Promise<ConversationThread> {
  const res = await request<{ data: ConversationThread }>(`/conversations/${id}`);
  return res.data;
}

export async function replyConversation(
  id: number,
  mensaje: string,
): Promise<{ ok: boolean; enviado: boolean; aviso: string | null }> {
  return request(`/conversations/${id}/reply`, {
    method: 'POST',
    body: JSON.stringify({ mensaje }),
  });
}

export { ApiError };

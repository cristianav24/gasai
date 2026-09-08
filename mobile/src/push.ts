import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { API_BASE_URL } from './config';
import { getToken } from './auth';

/**
 * Pide permiso de notificaciones, obtiene el token de Expo y lo registra en el
 * backend. Si algo falla (emulador, permiso denegado), no rompe el login.
 */
export async function registerForPush(): Promise<void> {
  try {
    if (!Device.isDevice) {
      return; // Los emuladores no dan token de push real.
    }

    const { status: existing } = await Notifications.getPermissionsAsync();
    let status = existing;
    if (existing !== 'granted') {
      status = (await Notifications.requestPermissionsAsync()).status;
    }
    if (status !== 'granted') {
      return;
    }

    const { data: expoToken } = await Notifications.getExpoPushTokenAsync();

    const sessionToken = await getToken();
    if (!sessionToken) {
      return;
    }

    await fetch(`${API_BASE_URL}/api/device-tokens`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${sessionToken}`,
      },
      body: JSON.stringify({
        token: expoToken,
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
      }),
    });
  } catch {
    // El push es un extra: nunca debe bloquear el uso de la app.
  }
}

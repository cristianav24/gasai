import Constants from 'expo-constants';

/**
 * URL base de la API de GasAI.
 *
 * Cámbiala en app.json → expo.extra.apiBaseUrl. En desarrollo, usa la IP de tu
 * PC en la red local (no "localhost": el celular no puede resolverlo), por
 * ejemplo http://192.168.1.100:8000, o una URL pública de ngrok.
 */
export const API_BASE_URL: string =
  (Constants.expoConfig?.extra?.apiBaseUrl as string | undefined) ??
  'http://192.168.1.100:8000';

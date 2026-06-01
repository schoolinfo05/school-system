import { Stack } from 'expo-router';

export default function RootLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="login" />
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="(teacher)" />

      {/* Enrollment screens — public, no login required */}
      <Stack.Screen name="enrollment" />
      <Stack.Screen name="enrollment-status" />
    </Stack>
  );
}
import { Stack } from 'expo-router';
import { ThemeProvider } from '../src/theme-context';

export default function RootLayout() {
  return (
    <ThemeProvider>
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="index" />
        <Stack.Screen name="login" />
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="(teacher)" />
        <Stack.Screen name="(admin)" />
        <Stack.Screen name="(parent)" />
        <Stack.Screen name="(staff)" />
        <Stack.Screen name="(registrar)" />
        <Stack.Screen name="forgot-password" />
        <Stack.Screen name="reset-password" />

        {/* Enrollment screens — public, no login required */}
        <Stack.Screen name="enrollment" />
        <Stack.Screen name="enrollment-status" />
      </Stack>
    </ThemeProvider>
  );
}

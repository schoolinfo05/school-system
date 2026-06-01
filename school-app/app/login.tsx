// @ts-nocheck
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  Alert, ActivityIndicator, KeyboardAvoidingView,
  ScrollView, Platform,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, { setToken } from '../src/api';

export default function Login() {
  const router = useRouter();
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading]     = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const handleLogin = async () => {
    setLoading(true);
    try {
      const res = await api.post('/login', { email, password });
      const { token, role } = res.data;

      await AsyncStorage.setItem('token', token);
      await AsyncStorage.setItem('role', role);
      await AsyncStorage.setItem('user', JSON.stringify(res.data.user));
      setToken(token);

      if (role === 'teacher') {
        router.replace('/(teacher)/classes');
      } else if (role === 'registrar' || role === 'admin') {
        router.replace('/(registrar)/enrollments');
      } else {
        router.replace('/(tabs)/today');
      }
    } catch (e) {
      Alert.alert('Login failed', 'Invalid email or password.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
    >
      <ScrollView
        contentContainerStyle={styles.inner}
        keyboardShouldPersistTaps="handled"   // ← prevents keyboard dismissing on tap
        showsVerticalScrollIndicator={false}
        bounces={false}
      >
        <Text style={styles.logo}>🏫</Text>
        <Text style={styles.title}>School System</Text>
        <Text style={styles.sub}>Sign in to your account</Text>

        <TextInput
          style={styles.input}
          placeholder="Email"
          placeholderTextColor="#aaa"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          returnKeyType="next"
          blurOnSubmit={false}
        />
        <View style={{ position: 'relative' }}>
          <TextInput
            style={[styles.input, { paddingRight: 46, marginBottom: 0 }]}
            placeholder="Password"
            placeholderTextColor="#aaa"
            value={password}
            onChangeText={setPassword}
            secureTextEntry={!showPassword}
            returnKeyType="done"
            onSubmitEditing={handleLogin}
          />
          <TouchableOpacity
            style={styles.eyeBtn}
            onPress={() => setShowPassword(p => !p)}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Text style={styles.eyeIcon}>{showPassword ? '🙈' : '👁️'}</Text>
          </TouchableOpacity>
        </View>
        <View style={{ height: 14 }} />

        <TouchableOpacity style={styles.btn} onPress={handleLogin} disabled={loading}>
          {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.btnText}>Sign in</Text>}
        </TouchableOpacity>

        {/* ── Enrollment CTA ── */}
        <View style={styles.dividerRow}>
          <View style={styles.dividerLine} />
          <Text style={styles.dividerText}>New student?</Text>
          <View style={styles.dividerLine} />
        </View>

        <TouchableOpacity
          style={styles.enrollBtn}
          onPress={() => router.push('/enrollment')}
        >
          <Text style={styles.enrollBtnText}>📋  Apply for Enrollment</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.statusLink}
          onPress={() => router.push('/enrollment-status')}
        >
          <Text style={styles.statusLinkText}>Already applied? Check your status →</Text>
        </TouchableOpacity>

        <Text style={styles.hint}>
          Student: student@school.com{'\n'}
          Teacher: teacher@school.com{'\n'}
          Password: password
        </Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  inner: {
    flexGrow: 1,           // ← lets ScrollView center content like flex:1 did
    justifyContent: 'center',
    padding: 28,
  },
  logo:  { fontSize: 48, textAlign: 'center', marginBottom: 12 },
  title: { fontSize: 28, fontWeight: '600', color: '#1a1a1a', textAlign: 'center' },
  sub:   { fontSize: 14, color: '#888', textAlign: 'center', marginBottom: 36, marginTop: 6 },
  input: {
    borderWidth: 0.5, borderColor: '#ddd', borderRadius: 12,
    padding: 14, marginBottom: 14,
    fontSize: 14, color: '#1a1a1a', backgroundColor: '#fafafa',
  },
  btn:     { backgroundColor: '#378ADD', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 4 },
  btnText: { color: '#fff', fontWeight: '600', fontSize: 15 },

  dividerRow:  { flexDirection: 'row', alignItems: 'center', marginTop: 24, marginBottom: 16, gap: 10 },
  dividerLine: { flex: 1, height: 1, backgroundColor: '#eee' },
  dividerText: { fontSize: 12, color: '#aaa', fontWeight: '500' },

  enrollBtn: {
    borderWidth: 1.5, borderColor: '#378ADD',
    borderRadius: 12, padding: 15,
    alignItems: 'center', marginBottom: 12,
  },
  enrollBtnText: { color: '#378ADD', fontWeight: '600', fontSize: 15 },

  statusLink:     { alignItems: 'center', marginBottom: 8 },
  statusLinkText: { color: '#378ADD', fontSize: 13 },

  eyeBtn: {
    position: 'absolute',
    right: 12, top: 0, bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
  },
  eyeIcon: { fontSize: 16 },
  hint: { textAlign: 'center', color: '#bbb', fontSize: 12, marginTop: 16, lineHeight: 20 },
});
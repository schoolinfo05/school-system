import { useCallback, useState, type ReactNode } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect, useRouter, useSegments } from 'expo-router';
import { Image, Platform, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useTheme } from '../../../src/theme-context';

type StatItem = {
  label: string;
  value: string | number;
  accent: string;
};

type Props = {
  title: string;
  subtitle: string;
  initials: string;
  stats: StatItem[];
  children?: ReactNode;
};

const HEADER_TOP = Platform.OS === 'android'
  ? (StatusBar.currentHeight ?? 24) + 18
  : 56;

export default function HeaderGradient({ title, subtitle, stats, children }: Props) {
  const { theme } = useTheme();
  const router = useRouter();
  const segments = useSegments();
  const [profilePhotoUrl, setProfilePhotoUrl] = useState<string | null>(null);
  const [profileRoute, setProfileRoute] = useState(routeForSegments(segments));

  useFocusEffect(
    useCallback(() => {
      let active = true;

      AsyncStorage.multiGet(['user', 'role', 'position'])
        .then(entries => {
          if (!active) return;
          const values = Object.fromEntries(entries);
          const user = values.user ? JSON.parse(values.user) : null;
          const resolvedUser = {
            ...user,
            role: user?.role || values.role,
            position: user?.position || values.position,
          };

          setProfilePhotoUrl(user?.profile_photo_url || null);
          setProfileRoute(routeForUser(resolvedUser, segments));
        })
        .catch(() => {
          if (active) {
            setProfilePhotoUrl(null);
            setProfileRoute(routeForSegments(segments));
          }
        });

      return () => {
        active = false;
      };
    }, [segments])
  );

  return (
    <LinearGradient colors={[theme.primary, theme.primary]} style={styles.container}>
      <View style={styles.topRow}>
        <View style={styles.textGroup}>
          <Text style={styles.greeting}>Welcome back</Text>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.subtitle}>{subtitle}</Text>
        </View>
        <TouchableOpacity
          style={styles.avatar}
          onPress={() => router.push(profileRoute)}
          activeOpacity={0.75}
          accessibilityRole="button"
          accessibilityLabel="Open profile"
        >
          {profilePhotoUrl ? (
            <Image source={{ uri: profilePhotoUrl }} style={styles.avatarImage} />
          ) : (
            <Ionicons name="person-circle" size={42} color="#FFFFFF" />
          )}
        </TouchableOpacity>
      </View>

      <View style={styles.statsRow}>
        {stats.map(item => (
          <View key={item.label} style={styles.statCard}>
            <Text style={[styles.statValue, { color: item.accent }]}>{item.value}</Text>
            <Text style={styles.statLabel}>{item.label}</Text>
          </View>
        ))}
      </View>

      {children ? <View style={styles.children}>{children}</View> : null}
    </LinearGradient>
  );
}

function routeForUser(user, segments = []) {
  const role = user?.role;
  const position = user?.position;

  if (role === 'admin') return '/(admin)/profile';
  if (role === 'registrar') return '/(registrar)/profile';
  if (role === 'parent') return '/(parent)/profile';
  if (role === 'staff' || ['librarian', 'property_custodian'].includes(position) || ['librarian', 'property_custodian'].includes(role)) {
    return '/(staff)/profile';
  }
  if (['faculty', 'teacher', 'head_teacher', 'dean'].includes(role) || ['head_teacher', 'dean'].includes(position)) {
    return '/(teacher)/profile';
  }

  return routeForSegments(segments);
}

function routeForSegments(segments = []) {
  const group = segments?.[0];

  if (group === '(admin)') return '/(admin)/profile';
  if (group === '(registrar)') return '/(registrar)/profile';
  if (group === '(staff)') return '/(staff)/profile';
  if (group === '(teacher)') return '/(teacher)/profile';
  if (group === '(parent)') return '/(parent)/profile';

  return '/(tabs)/profile';
}

const styles = StyleSheet.create({
  container: {
    borderRadius: 18,
    padding: 16,
    paddingTop: HEADER_TOP,
    marginBottom: 10,
    overflow: 'hidden',
  },
  topRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  textGroup: {
    flex: 1,
    paddingRight: 16,
  },
  greeting: {
    color: 'rgba(255,255,255,0.85)',
    fontSize: 13,
    marginBottom: 6,
  },
  title: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: '900',
    marginBottom: 4,
  },
  subtitle: {
    color: 'rgba(255,255,255,0.88)',
    fontSize: 14,
    lineHeight: 20,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255,255,255,0.28)',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 30,
  },
  statsRow: {
    marginTop: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  statCard: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderRadius: 10,
    paddingVertical: 7,
    paddingHorizontal: 9,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
  },
  statValue: {
    fontSize: 14,
    fontWeight: '900',
  },
  statLabel: {
    marginTop: 3,
    color: 'rgba(255,255,255,0.92)',
    fontSize: 9,
    fontWeight: '700',
  },
  children: {
    marginTop: 18,
  },
});

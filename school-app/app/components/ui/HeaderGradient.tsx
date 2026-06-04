import type { ReactNode } from 'react';
import { LinearGradient } from 'expo-linear-gradient';
import { Platform, StatusBar, StyleSheet, Text, View } from 'react-native';
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

export default function HeaderGradient({ title, subtitle, initials, stats, children }: Props) {
  const { theme } = useTheme();

  return (
    <LinearGradient colors={[theme.primary, theme.primary]} style={styles.container}>
      <View style={styles.topRow}>
        <View style={styles.textGroup}>
          <Text style={styles.greeting}>Welcome back</Text>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.subtitle}>{subtitle}</Text>
        </View>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
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

const styles = StyleSheet.create({
  container: {
    borderRadius: 24,
    padding: 20,
    paddingTop: HEADER_TOP,
    marginBottom: 18,
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
  },
  avatarText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '900',
  },
  statsRow: {
    marginTop: 20,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  statCard: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.24)',
    borderRadius: 16,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  statValue: {
    fontSize: 18,
    fontWeight: '900',
  },
  statLabel: {
    marginTop: 6,
    color: 'rgba(255,255,255,0.92)',
    fontSize: 11,
    fontWeight: '700',
  },
  children: {
    marginTop: 18,
  },
});

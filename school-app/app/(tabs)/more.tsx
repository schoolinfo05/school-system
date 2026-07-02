// @ts-nocheck
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useTheme } from '../../src/theme-context';

const MORE_ROUTES = [
  { name: 'study-load', label: 'Study Load', icon: 'library-outline' },
  { name: 'assignments', label: 'Assignments', icon: 'document-text-outline' },
  { name: 'grades', label: 'Grades', icon: 'bar-chart-outline' },
  { name: 'rewards', label: 'Rewards', icon: 'trophy-outline' },
  { name: 'market', label: 'Marketplace', icon: 'storefront-outline' },
];

export default function More() {
  const router = useRouter();
  const { theme } = useTheme();

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: theme.bg }]}
      contentContainerStyle={styles.content}
      showsVerticalScrollIndicator={false}
    >
      <Text style={[styles.title, { color: theme.text }]}>More</Text>

      <View style={styles.grid}>
        {MORE_ROUTES.map((item) => (
          <TouchableOpacity
            key={item.name}
            style={[styles.card, { backgroundColor: theme.card, borderColor: theme.border }]}
            onPress={() => router.replace(`/(tabs)/${item.name}`)}
            activeOpacity={0.75}
          >
            <View style={[styles.iconWrap, { backgroundColor: `${theme.primary}16` }]}>
              <Ionicons name={item.icon} size={22} color={theme.primary} />
            </View>
            <Text style={[styles.cardText, { color: theme.text }]} numberOfLines={1}>
              {item.label}
            </Text>
            <Ionicons name="chevron-forward" size={18} color={theme.textSub} />
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  content: { padding: 20, paddingBottom: 40 },
  title: { fontSize: 24, fontWeight: '800', marginBottom: 14 },
  grid: { gap: 10 },
  card: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    gap: 12,
  },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardText: { flex: 1, fontSize: 15, fontWeight: '700' },
});

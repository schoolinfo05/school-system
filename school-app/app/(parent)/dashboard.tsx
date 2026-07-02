// @ts-nocheck
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import HeaderGradient from '../components/ui/HeaderGradient';
import api from '../../src/api';
import { useTheme } from '../../src/theme-context';

export default function ParentDashboard() {
  const { theme } = useTheme();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.get('/dashboard/parent');
      setData(res.data);
    } catch (e) {
      console.log('Parent dashboard error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const children = data?.children ?? [];

  return (
    <View style={[s.container, { backgroundColor: theme.bg }]}>
      <HeaderGradient
        title="Parent Portal"
        subtitle="Grades, attendance, fees, and points for linked students."
        initials="PA"
        stats={[
          { label: 'Children', value: children.length, accent: '#C7D2FE' },
          { label: 'Alerts', value: 0, accent: '#FDE68A' },
        ]}
      />
      {loading ? (
        <View style={s.center}><ActivityIndicator size="large" color={theme.primary} /></View>
      ) : (
        <ScrollView
          contentContainerStyle={s.body}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
        >
          {children.length === 0 ? (
            <View style={[s.card, { backgroundColor: theme.card, borderColor: theme.border }]}>
              <Text style={[s.title, { color: theme.text }]}>No linked students</Text>
              <Text style={[s.sub, { color: theme.textSub }]}>Ask an administrator to link this parent account to a student profile.</Text>
            </View>
          ) : children.map((child, index) => (
            <View key={child.student?.id ?? index} style={[s.card, { backgroundColor: theme.card, borderColor: theme.border }]}>
              <Text style={[s.title, { color: theme.text }]}>{child.student?.first_name} {child.student?.last_name}</Text>
              <Text style={[s.sub, { color: theme.textSub }]}>
                {child.student?.student_id} · Grade/Year {child.student?.grade_level} · {child.student?.section}
              </Text>
              <View style={s.grid}>
                <Metric label="Attendance" value={`${child.attendance_pct ?? 0}%`} theme={theme} />
                <Metric label="Balance" value={`PHP ${Number(child.fee_summary?.balance || 0).toFixed(2)}`} theme={theme} />
                <Metric label="Points" value={child.reward_summary?.points ?? 0} theme={theme} />
                <Metric label="Point value" value={`PHP ${Number(child.reward_summary?.peso_value || 0).toFixed(2)}`} theme={theme} />
              </View>
            </View>
          ))}
        </ScrollView>
      )}
    </View>
  );
}

function Metric({ label, value, theme }) {
  return (
    <View style={[s.metric, { backgroundColor: theme.bg }]}>
      <Text style={[s.metricValue, { color: theme.text }]}>{value}</Text>
      <Text style={[s.metricLabel, { color: theme.textSub }]}>{label}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  body: { padding: 16, gap: 12, paddingBottom: 90 },
  card: { borderWidth: 1, borderRadius: 14, padding: 16 },
  title: { fontSize: 17, fontWeight: '900' },
  sub: { fontSize: 12, fontWeight: '600', marginTop: 4, lineHeight: 18 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 14 },
  metric: { width: '47%', borderRadius: 12, padding: 12 },
  metricValue: { fontSize: 16, fontWeight: '900' },
  metricLabel: { fontSize: 11, fontWeight: '700', marginTop: 3 },
});

// @ts-nocheck
// app/(teacher)/classes.tsx — Teacher dashboard (fully polished)

import { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet,
  ActivityIndicator, TouchableOpacity, RefreshControl,
} from 'react-native';
import { useRouter } from 'expo-router';
import api from '../../src/api';
import { Colors, Font, Radius, Shadow, HEADER_TOP } from '../../src/theme';
import { useTheme } from '../../src/theme-context';

export default function Classes() {
  const router = useRouter();
  const { theme } = useTheme();
  const ACCENT = [theme.green, theme.primary, theme.orange, theme.purple];
  const [data, setData]             = useState(null);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboard = useCallback(async () => {
    try {
      const res = await api.get('/teacher/dashboard');
      setData(res.data);
    } catch (e) {
      console.log('Teacher dashboard error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { fetchDashboard(); }, []);

  if (loading) return (
    <View style={[styles.center, { backgroundColor: theme.bg }]}>
      <ActivityIndicator size="large" color={theme.primary} />
    </View>
  );

  const hour     = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: theme.bg }]}
      contentContainerStyle={{ paddingBottom: 32 }}
      refreshControl={
        <RefreshControl refreshing={refreshing}
          onRefresh={() => { setRefreshing(true); fetchDashboard(); }} />
      }
    >
      {/* ── Header ── */}
      <View style={[styles.header, { backgroundColor: theme.primary }]}> 
        <Text style={styles.greeting}>{greeting} 👋</Text>
        <Text style={styles.name}>{data?.teacher}</Text>
        <Text style={styles.role}>Teacher · S.Y. 2025–2026</Text>
      </View>

      {/* ── Stat cards ── */}
      <View style={styles.statsRow}>
        <View style={[styles.statCard, { backgroundColor: theme.card }]}>
          <Text style={[styles.statVal, { color: theme.text }]}>{data?.total_classes ?? 0}</Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>Classes</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: theme.card }]}>
          <Text style={[styles.statVal, { color: theme.text }]}>{data?.total_students ?? 0}</Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>Students</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: theme.card, borderBottomWidth: 3, borderBottomColor: theme.success }]}> 
          <Text style={[styles.statVal, { color: theme.success }]}>{data?.today_attendance ?? 0}</Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>Today</Text>
        </View>
      </View>

      <View style={styles.sectionHeader}>
        <Text style={[styles.sectionTitle, { color: theme.textSub }]}>My Classes</Text>
      </View>
      {data?.classes?.length === 0
        ? (
          <View style={[styles.emptyCard, { backgroundColor: theme.card }]}>
            <Text style={styles.emptyIcon}>📚</Text>
            <Text style={[styles.emptyText, { color: theme.textMuted }]}>No classes assigned yet.</Text>
          </View>
        )
        : data?.classes?.map((c, i) => (
          <View key={i} style={[styles.classCard, { backgroundColor: theme.card }]}> 
            <View style={[styles.classAccent, { backgroundColor: ACCENT[i % ACCENT.length] }]} />
            <View style={styles.classBody}>
              <Text style={[styles.className, { color: theme.text }]}>{c.subject}</Text>
              <Text style={[styles.classMeta, { color: theme.textSub }]}>
                {c.course ? `Year ${c.grade_level}` : `Grade ${c.grade_level}`} - {c.section} · {c.room || 'No room'} · {c.schedule}
              </Text>
              <View style={styles.classActions}>
                <TouchableOpacity
                  style={[styles.actionBtn, { backgroundColor: theme.primary + '20' }]}
                  onPress={() => router.push({
                    pathname: '/(teacher)/grades',
                    params: { classId: String(c.id), subject: c.subject },
                  })}
                >
                  <Text style={[styles.actionText, { color: theme.primary }]}>📝 Enter grades</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.actionBtn, { backgroundColor: theme.primary + '20' }]}
                  onPress={() => router.push({
                    pathname: '/(teacher)/attendance',
                    params: { classId: String(c.id), subject: c.subject },
                  })}
                >
                  <Text style={[styles.actionText, { color: theme.primary }]}>📅 Attendance</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ))
      }

      {/* ── Recent grades ── */}
      {data?.recent_grades?.length > 0 && (
        <>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: theme.textSub }]}>Recently Entered Grades</Text>
          </View>
          <View style={[styles.card, { backgroundColor: theme.card }]}>
            {data.recent_grades.map((g, i) => (
              <View key={i} style={[styles.gradeRow, { borderColor: theme.border }]}>
                <View style={[styles.gradeAvatar, { backgroundColor: theme.primaryLight }]}>
                  <Text style={[styles.gradeAvatarText, { color: theme.primary }]}>
                    {g.student?.first_name?.[0]}{g.student?.last_name?.[0]}
                  </Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.gradeName, { color: theme.text }]}>
                    {g.student?.first_name} {g.student?.last_name}
                  </Text>
                  <Text style={[styles.gradeMeta, { color: theme.textMuted }]}>{g.school_class?.subject} · Q{g.quarter}</Text>
                </View>
                <Text style={[styles.gradeScore, {
                  color: g.score >= 85 ? theme.success : g.score >= 75 ? theme.warning : theme.danger,
                }]}>
                  {g.score}
                </Text>
              </View>
            ))}
          </View>
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:       { flex: 1, backgroundColor: Colors.bg },
  center:          { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.bg },

  header:          {
    backgroundColor: Colors.green,
    paddingTop: HEADER_TOP,
    paddingBottom: 24,
    paddingHorizontal: 20,
  },
  greeting:        { color: 'rgba(255,255,255,0.8)', fontSize: Font.sm },
  name:            { color: '#fff', fontSize: Font.xl, fontWeight: '700', marginTop: 4 },
  role:            { color: 'rgba(255,255,255,0.7)', fontSize: Font.xs, marginTop: 6 },

  statsRow:        {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: -16,
    gap: 10,
    marginBottom: 20,
  },
  statCard:        {
    flex: 1,
    backgroundColor: Colors.card,
    borderRadius: Radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    ...Shadow.card,
  },
  statVal:         { fontSize: Font.xl, fontWeight: '700', color: Colors.text },
  statLabel:       { fontSize: Font.xs, color: Colors.textSub, marginTop: 4 },

  sectionHeader:   { paddingHorizontal: 16, marginBottom: 10 },
  sectionTitle:    { fontSize: Font.sm, fontWeight: '700', color: Colors.textSub, textTransform: 'uppercase', letterSpacing: 0.5 },

  classCard:       {
    flexDirection: 'row',
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    marginHorizontal: 16,
    marginBottom: 10,
    overflow: 'hidden',
    ...Shadow.card,
  },
  classAccent:     { width: 6 },
  classBody:       { flex: 1, padding: 14 },
  className:       { fontSize: Font.md, fontWeight: '700', color: Colors.text },
  classMeta:       { fontSize: Font.xs, color: Colors.textSub, marginTop: 4, marginBottom: 12 },
  classActions:    { flexDirection: 'row', gap: 8 },
  actionBtn:       { flex: 1, borderRadius: Radius.sm, paddingVertical: 8, alignItems: 'center' },
  actionText:      { fontSize: Font.xs, fontWeight: '600' },

  card:            {
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    marginHorizontal: 16,
    marginBottom: 14,
    padding: 4,
    ...Shadow.card,
  },
  gradeRow:        {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderColor: Colors.border,
  },
  gradeAvatar:     {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  gradeAvatarText: { fontSize: Font.xs, fontWeight: '700' },
  gradeName:       { fontSize: Font.sm, fontWeight: '500', color: Colors.text },
  gradeMeta:       { fontSize: Font.xs, color: Colors.textMuted, marginTop: 1 },
  gradeScore:      { fontSize: Font.lg, fontWeight: '800' },

  emptyCard:       {
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    marginHorizontal: 16,
    padding: 32,
    alignItems: 'center',
    ...Shadow.card,
  },
  emptyIcon:       { fontSize: 40, marginBottom: 10 },
  emptyText:       { fontSize: Font.sm, color: Colors.textMuted },
});

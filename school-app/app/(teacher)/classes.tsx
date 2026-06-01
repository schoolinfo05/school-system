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

const ACCENT = [Colors.green, Colors.blue, Colors.orange, Colors.purple];

export default function Classes() {
  const router = useRouter();
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
    <View style={styles.center}>
      <ActivityIndicator size="large" color={Colors.green} />
    </View>
  );

  const hour     = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 32 }}
      refreshControl={
        <RefreshControl refreshing={refreshing}
          onRefresh={() => { setRefreshing(true); fetchDashboard(); }} />
      }
    >
      {/* ── Header ── */}
      <View style={styles.header}>
        <Text style={styles.greeting}>{greeting} 👋</Text>
        <Text style={styles.name}>{data?.teacher}</Text>
        <Text style={styles.role}>Teacher · S.Y. 2025–2026</Text>
      </View>

      {/* ── Stat cards ── */}
      <View style={styles.statsRow}>
        <View style={styles.statCard}>
          <Text style={styles.statVal}>{data?.total_classes ?? 0}</Text>
          <Text style={styles.statLabel}>Classes</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statVal}>{data?.total_students ?? 0}</Text>
          <Text style={styles.statLabel}>Students</Text>
        </View>
        <View style={[styles.statCard, { borderBottomWidth: 3, borderBottomColor: Colors.green }]}>
          <Text style={[styles.statVal, { color: Colors.green }]}>{data?.today_attendance ?? 0}</Text>
          <Text style={styles.statLabel}>Present today</Text>
        </View>
      </View>

      {/* ── My classes ── */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>My Classes</Text>
      </View>

      {data?.classes?.length === 0
        ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyIcon}>📚</Text>
            <Text style={styles.emptyText}>No classes assigned yet.</Text>
          </View>
        )
        : data?.classes?.map((c, i) => (
          <View key={i} style={styles.classCard}>
            <View style={[styles.classAccent, { backgroundColor: ACCENT[i % ACCENT.length] }]} />
            <View style={styles.classBody}>
              <Text style={styles.className}>{c.subject}</Text>
              <Text style={styles.classMeta}>
                {c.course ? `Year ${c.grade_level}` : `Grade ${c.grade_level}`} - {c.section} · {c.room || 'No room'} · {c.schedule}
              </Text>
              <View style={styles.classActions}>
                <TouchableOpacity
                  style={[styles.actionBtn, { backgroundColor: Colors.blueLight }]}
                  onPress={() => router.push({
                    pathname: '/(teacher)/grades',
                    params: { classId: String(c.id), subject: c.subject },
                  })}
                >
                  <Text style={[styles.actionText, { color: Colors.blue }]}>📝 Enter grades</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.actionBtn, { backgroundColor: Colors.greenLight }]}
                  onPress={() => router.push({
                    pathname: '/(teacher)/attendance',
                    params: { classId: String(c.id), subject: c.subject },
                  })}
                >
                  <Text style={[styles.actionText, { color: Colors.green }]}>📅 Attendance</Text>
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
            <Text style={styles.sectionTitle}>Recently Entered Grades</Text>
          </View>
          <View style={styles.card}>
            {data.recent_grades.map((g, i) => (
              <View key={i} style={styles.gradeRow}>
                <View style={styles.gradeAvatar}>
                  <Text style={styles.gradeAvatarText}>
                    {g.student?.first_name?.[0]}{g.student?.last_name?.[0]}
                  </Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.gradeName}>
                    {g.student?.first_name} {g.student?.last_name}
                  </Text>
                  <Text style={styles.gradeMeta}>{g.school_class?.subject} · Q{g.quarter}</Text>
                </View>
                <Text style={[styles.gradeScore, {
                  color: g.score >= 85 ? Colors.green : g.score >= 75 ? Colors.warning : Colors.danger,
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
    backgroundColor: Colors.greenLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  gradeAvatarText: { fontSize: Font.xs, fontWeight: '700', color: Colors.green },
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

// @ts-nocheck
// app/(tabs)/today.tsx — Student dashboard (fully polished)

import { useRouter } from 'expo-router';
import { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet,
  ActivityIndicator, RefreshControl, TouchableOpacity,
  Platform, StatusBar,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../../src/api';
import { Colors, Font, Radius, Shadow, HEADER_TOP } from '../../src/theme';
import { useTheme } from '../../src/theme-context';

function gradeColor(score, theme) {
  if (score >= 90) return theme.success;
  if (score >= 80) return theme.primary;
  if (score >= 75) return theme.warning;
  return theme.danger;
}

export default function Today() {
  const router = useRouter();
  const { theme } = useTheme();
  const [data, setData]             = useState(null);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboard = useCallback(async () => {
    try {
      const res = await api.get('/dashboard/student');
      setData(res.data);
    } catch (e) {
      console.log('Dashboard error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    AsyncStorage.getItem('role').then(role => {
      if (role === 'teacher') {
        router.replace('/(teacher)/classes');
      } else {
        fetchDashboard();
      }
    });
  }, []);

  if (loading) return (
    <View style={[styles.center, { backgroundColor: theme.bg }]}>
      <ActivityIndicator size="large" color={theme.primary} />
    </View>
  );

  const student   = data?.student;
  const allGrades = data?.grades ? Object.values(data.grades).flat() : [];
  const gwa = allGrades.length > 0
    ? (allGrades.reduce((s, g) => s + parseFloat(g.score), 0) / allGrades.length).toFixed(1)
    : '—';
  const hour     = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: theme.bg }]}
      contentContainerStyle={{ paddingBottom: 24 }}
      refreshControl={
        <RefreshControl refreshing={refreshing}
          onRefresh={() => { setRefreshing(true); fetchDashboard(); }} />
      }
    >
      {/* ── Header ── */}
      <View style={[styles.header, { backgroundColor: theme.primary }]}>
        <Text style={styles.greeting}>{greeting} 👋</Text>
        <Text style={styles.name}>
          {student?.first_name} {student?.last_name}
        </Text>
        <Text style={styles.section}>
          Grade {student?.grade_level} — {student?.section} · {student?.school_year}
        </Text>
      </View>

      {/* ── Stat cards ── */}
      <View style={styles.statsRow}>
        <View style={[styles.statCard, { backgroundColor: theme.card }]}> 
          <Text style={[styles.statVal, { color: theme.text }]}>{data?.attendance_pct ?? 0}%</Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>Attendance</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: theme.card }]}> 
          <Text style={[styles.statVal, { color: theme.text }]}>{gwa}</Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>GWA</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: theme.card },

          { borderBottomWidth: 3, borderBottomColor: (data?.pending_fees?.length ?? 0) > 0 ? theme.danger : theme.success }]}>
          <Text style={[styles.statVal,
            { color: (data?.pending_fees?.length ?? 0) > 0 ? theme.danger : theme.success }]}>
            {data?.pending_fees?.length ?? 0}
          </Text>
          <Text style={[styles.statLabel, { color: theme.textSub }]}>Pending fees</Text>
        </View>
      </View>

      {/* ── Outstanding fees ── */}
      {(data?.pending_fees?.length ?? 0) > 0 && (
        <View style={[styles.card, styles.feeCard, { backgroundColor: theme.card, borderLeftColor: theme.danger }]}>
          <View style={styles.cardHeader}>
            <Text style={[styles.cardTitle, { color: theme.text }]}>⚠️ Outstanding fees</Text>
          </View>
          {data.pending_fees.map((fee, i) => (
            <View key={i} style={[styles.feeRow, { borderColor: theme.border }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.feeName, { color: theme.text }]}>{fee.type}</Text>
                <Text style={[styles.feeSub, { color: theme.textMuted }]}>Q{fee.quarter} · Due {fee.due_date}</Text>
              </View>
              <View style={[styles.badge,
                { backgroundColor: fee.status === 'unpaid' ? theme.dangerLight : theme.warningLight }]}>
                <Text style={[styles.badgeText,
                  { color: fee.status === 'unpaid' ? theme.danger : theme.warning }]}>
                  ₱{Number(fee.amount).toLocaleString()}
                </Text>
              </View>
            </View>
          ))}
        </View>
      )}

      {/* ── Latest grades ── */}
      <View style={[styles.card, { backgroundColor: theme.card }]}> 
        <View style={styles.cardHeader}>
          <Text style={[styles.cardTitle, { color: theme.text }]}>Latest grades (Q4)</Text>
          <TouchableOpacity onPress={() => router.push('/(tabs)/grades')}>
            <Text style={[styles.seeAll, { color: theme.primary }]}>See all →</Text>
          </TouchableOpacity>
        </View>
        {data?.grades?.['4']?.length > 0
          ? data.grades['4'].map((g, i) => (
            <View key={i} style={[styles.gradeRow, { borderColor: theme.border }]}>
              <View style={[styles.gradeBar, {
                width: `${Math.max(5, Math.min(100, ((g.score - 70) / 30) * 100))}%`,
                backgroundColor: gradeColor(g.score, theme),
              }]} />
              <Text style={[styles.subjectName, { color: theme.textSub }]}>{g.school_class?.subject ?? '—'}</Text>
              <Text style={[styles.gradeScore, {
                color: gradeColor(g.score, theme),
              }]}>
                {g.score}
              </Text>
            </View>
          ))
          : <Text style={[styles.empty, { color: theme.textMuted }]}>No Q4 grades recorded yet.</Text>
        }
      </View>

      {/* ── Quick actions ── */}
      <View style={styles.quickRow}>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: theme.primaryLight }]}
          onPress={() => router.push('/(tabs)/study')}>
          <Text style={styles.quickIcon}>🎓</Text>
          <Text style={[styles.quickLabel, { color: theme.primary }]}>Study AI</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: theme.primaryLight }]}
          onPress={() => router.push('/(tabs)/market')}>
          <Text style={styles.quickIcon}>🛍️</Text>
          <Text style={[styles.quickLabel, { color: theme.primary }]}>Marketplace</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: theme.primaryLight }]}
          onPress={() => router.push('/(tabs)/grades')}>
          <Text style={styles.quickIcon}>📊</Text>
          <Text style={[styles.quickLabel, { color: theme.primary }]}>All grades</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:   { flex: 1, backgroundColor: Colors.bg },
  center:      { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.bg },

  // Header
  header:      {
    backgroundColor: Colors.blue,
    paddingTop: HEADER_TOP,
    paddingBottom: 24,
    paddingHorizontal: 20,
  },
  greeting:    { color: 'rgba(255,255,255,0.8)', fontSize: Font.sm },
  name:        { color: '#fff', fontSize: Font.xl, fontWeight: '700', marginTop: 4 },
  section:     { color: 'rgba(255,255,255,0.7)', fontSize: Font.xs, marginTop: 6 },

  // Stats
  statsRow:    {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: -16,
    gap: 10,
    marginBottom: 16,
  },
  statCard:    {
    flex: 1,
    backgroundColor: Colors.card,
    borderRadius: Radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    ...Shadow.card,
  },
  statVal:     { fontSize: Font.xl, fontWeight: '700', color: Colors.text },
  statLabel:   { fontSize: Font.xs, color: Colors.textSub, marginTop: 4 },

  // Cards
  card:        {
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    marginHorizontal: 16,
    marginBottom: 14,
    padding: 16,
    ...Shadow.card,
  },
  feeCard:     { borderLeftWidth: 4, borderLeftColor: Colors.danger },
  cardHeader:  {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  cardTitle:   { fontSize: Font.sm, fontWeight: '600', color: Colors.text },
  seeAll:      { fontSize: Font.xs, color: Colors.blue, fontWeight: '500' },

  // Fees
  feeRow:      {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderColor: Colors.border,
  },
  feeName:     { fontSize: Font.sm, fontWeight: '500', color: Colors.text },
  feeSub:      { fontSize: Font.xs, color: Colors.textMuted, marginTop: 2 },
  badge:       { borderRadius: Radius.full, paddingHorizontal: 10, paddingVertical: 4 },
  badgeRed:    { backgroundColor: Colors.dangerLight },
  badgeYellow: { backgroundColor: Colors.warningLight },
  badgeText:   { fontSize: Font.xs, fontWeight: '600' },
  badgeRedText:    { color: Colors.danger },
  badgeYellowText: { color: Colors.warning },

  // Grades
  gradeRow:    {
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderColor: Colors.border,
    gap: 8,
  },
  gradeBar:    { height: 3, borderRadius: 2, marginBottom: 4 },
  subjectName: { fontSize: Font.sm, color: Colors.textSub, flex: 1 },
  gradeScore:  { fontSize: Font.md, fontWeight: '700' },

  // Quick actions
  quickRow:    {
    flexDirection: 'row',
    marginHorizontal: 16,
    gap: 10,
    marginBottom: 8,
  },
  quickCard:   {
    flex: 1,
    borderRadius: Radius.md,
    paddingVertical: 16,
    alignItems: 'center',
    gap: 6,
  },
  quickIcon:   { fontSize: 24 },
  quickLabel:  { fontSize: Font.xs, fontWeight: '600' },

  empty:       { fontSize: Font.sm, color: Colors.textMuted, textAlign: 'center', paddingVertical: 16 },
});

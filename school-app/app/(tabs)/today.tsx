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

export default function Today() {
  const router = useRouter();
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
    <View style={styles.center}>
      <ActivityIndicator size="large" color={Colors.blue} />
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
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 24 }}
      refreshControl={
        <RefreshControl refreshing={refreshing}
          onRefresh={() => { setRefreshing(true); fetchDashboard(); }} />
      }
    >
      {/* ── Header ── */}
      <View style={styles.header}>
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
        <View style={styles.statCard}>
          <Text style={styles.statVal}>{data?.attendance_pct ?? 0}%</Text>
          <Text style={styles.statLabel}>Attendance</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statVal}>{gwa}</Text>
          <Text style={styles.statLabel}>GWA</Text>
        </View>
        <View style={[styles.statCard,
          { borderBottomWidth: 3, borderBottomColor: (data?.pending_fees?.length ?? 0) > 0 ? Colors.danger : Colors.green }]}>
          <Text style={[styles.statVal,
            { color: (data?.pending_fees?.length ?? 0) > 0 ? Colors.danger : Colors.green }]}>
            {data?.pending_fees?.length ?? 0}
          </Text>
          <Text style={styles.statLabel}>Pending fees</Text>
        </View>
      </View>

      {/* ── Outstanding fees ── */}
      {(data?.pending_fees?.length ?? 0) > 0 && (
        <View style={[styles.card, styles.feeCard]}>
          <View style={styles.cardHeader}>
            <Text style={styles.cardTitle}>⚠️ Outstanding fees</Text>
          </View>
          {data.pending_fees.map((fee, i) => (
            <View key={i} style={styles.feeRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.feeName}>{fee.type}</Text>
                <Text style={styles.feeSub}>Q{fee.quarter} · Due {fee.due_date}</Text>
              </View>
              <View style={[styles.badge,
                fee.status === 'unpaid' ? styles.badgeRed : styles.badgeYellow]}>
                <Text style={[styles.badgeText,
                  fee.status === 'unpaid' ? styles.badgeRedText : styles.badgeYellowText]}>
                  ₱{Number(fee.amount).toLocaleString()}
                </Text>
              </View>
            </View>
          ))}
        </View>
      )}

      {/* ── Latest grades ── */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.cardTitle}>Latest grades (Q4)</Text>
          <TouchableOpacity onPress={() => router.push('/(tabs)/grades')}>
            <Text style={styles.seeAll}>See all →</Text>
          </TouchableOpacity>
        </View>
        {data?.grades?.['4']?.length > 0
          ? data.grades['4'].map((g, i) => (
            <View key={i} style={styles.gradeRow}>
              <View style={[styles.gradeBar, {
                width: `${Math.max(5, Math.min(100, ((g.score - 70) / 30) * 100))}%`,
                backgroundColor: g.score >= 90 ? Colors.green : g.score >= 80 ? Colors.blue : g.score >= 75 ? Colors.warning : Colors.danger,
              }]} />
              <Text style={styles.subjectName}>{g.school_class?.subject ?? '—'}</Text>
              <Text style={[styles.gradeScore, {
                color: g.score >= 90 ? Colors.green : g.score >= 80 ? Colors.blue : g.score >= 75 ? Colors.warning : Colors.danger,
              }]}>
                {g.score}
              </Text>
            </View>
          ))
          : <Text style={styles.empty}>No Q4 grades recorded yet.</Text>
        }
      </View>

      {/* ── Quick actions ── */}
      <View style={styles.quickRow}>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: Colors.purpleLight }]}
          onPress={() => router.push('/(tabs)/study')}>
          <Text style={styles.quickIcon}>🎓</Text>
          <Text style={[styles.quickLabel, { color: Colors.purple }]}>Study AI</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: Colors.blueLight }]}
          onPress={() => router.push('/(tabs)/market')}>
          <Text style={styles.quickIcon}>🛍️</Text>
          <Text style={[styles.quickLabel, { color: Colors.blue }]}>Marketplace</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quickCard, { backgroundColor: Colors.greenLight }]}
          onPress={() => router.push('/(tabs)/grades')}>
          <Text style={styles.quickIcon}>📊</Text>
          <Text style={[styles.quickLabel, { color: Colors.green }]}>All grades</Text>
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

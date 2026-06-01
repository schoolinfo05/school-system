// @ts-nocheck
// app/(tabs)/grades.tsx — Student grades screen (fully polished)

import { useEffect, useState } from 'react';
import {
  View, Text, ScrollView, StyleSheet,
  ActivityIndicator, TouchableOpacity,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import api from '../../src/api';
import { Colors, Font, Radius, Shadow, HEADER_TOP } from '../../src/theme';

const QUARTERS = ['1', '2', '3', '4'];
const ACCENT = [Colors.green, Colors.blue, Colors.orange, Colors.purple];

function getRemark(score) {
  if (score >= 90) return { text: 'Outstanding',         color: Colors.green   };
  if (score >= 85) return { text: 'Very Satisfactory',   color: Colors.blue    };
  if (score >= 80) return { text: 'Satisfactory',        color: Colors.textSub };
  if (score >= 75) return { text: 'Fairly Satisfactory', color: Colors.warning  };
  return               { text: 'Did Not Meet',           color: Colors.danger  };
}

export default function Grades() {
  const router    = useRouter();
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeQ, setActiveQ] = useState('4');

  useEffect(() => {
    AsyncStorage.getItem('role').then(role => {
      if (role === 'teacher') {
        router.replace('/(teacher)/grades');
        return;
      }
      api.get('/dashboard/student')
        .then(res => setData(res.data))
        .finally(() => setLoading(false));
    });
  }, []);

  if (loading) return (
    <View style={styles.center}>
      <ActivityIndicator size="large" color={Colors.green} />
    </View>
  );

  const grades    = data?.grades?.[activeQ] ?? [];
  const allGrades = data?.grades ? Object.values(data.grades).flat() : [];
  const gwa = allGrades.length > 0
    ? (allGrades.reduce((s, g) => s + parseFloat(g.score), 0) / allGrades.length).toFixed(1)
    : '—';

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 32 }}>

      {/* ── Header ── */}
      <View style={styles.header}>
        <Text style={styles.title}>My Grades</Text>
        <Text style={styles.sub}>S.Y. {data?.student?.school_year}</Text>
        <View style={styles.gwaRow}>
          <View>
            <Text style={styles.gwaNum}>{gwa}</Text>
            <Text style={styles.gwaLabel}>General Weighted Average</Text>
          </View>
          <View style={styles.gwaBadge}>
            <Text style={styles.gwaBadgeText}>
              {parseFloat(gwa) >= 90 ? '🏆 Outstanding'
                : parseFloat(gwa) >= 85 ? '⭐ Very Good'
                : parseFloat(gwa) >= 80 ? '✅ Good'
                : '📚 Keep going'}
            </Text>
          </View>
        </View>
      </View>

      {/* ── Quarter tabs ── */}
      <View style={styles.tabs}>
        {QUARTERS.map(q => (
          <TouchableOpacity
            key={q}
            style={[styles.tab, activeQ === q && { backgroundColor: Colors.green, borderColor: Colors.green }]}
            onPress={() => setActiveQ(q)}
          >
            <Text style={[styles.tabText, activeQ === q && styles.tabTextActive]}>
              Q{q}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* ── Subject cards ── */}
      <View style={styles.section}>
        {grades.length === 0
          ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyIcon}>📋</Text>
              <Text style={styles.emptyText}>No grades for Quarter {activeQ} yet.</Text>
            </View>
          )
          : grades.map((g, i) => {
            const rem = getRemark(parseFloat(g.score));
            const pct = Math.max(5, Math.min(100, ((parseFloat(g.score) - 70) / 30) * 100));
            return (
              <View key={i} style={styles.subjectCard}>
                <View style={[styles.accentBar, { backgroundColor: ACCENT[i % ACCENT.length] }]} />
                <View style={styles.subjectBody}>
                  <View style={styles.subjectTop}>
                    <Text style={styles.subjectName}>{g.school_class?.subject ?? '—'}</Text>
                    <Text style={[styles.score, { color: rem.color }]}>{g.score}</Text>
                  </View>
                  <View style={styles.barTrack}>
                    <View style={[styles.barFill, {
                      width: `${pct}%`,
                      backgroundColor: ACCENT[i % ACCENT.length],
                    }]} />
                  </View>
                  <Text style={[styles.remark, { color: rem.color }]}>{rem.text}</Text>
                </View>
              </View>
            );
          })
        }
      </View>

    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: Colors.bg },
  center:         { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.bg },

  header:         {
    backgroundColor: Colors.green,
    paddingTop: HEADER_TOP,
    paddingBottom: 28,
    paddingHorizontal: 20,
  },
  title:          { color: '#fff', fontSize: Font.xl, fontWeight: '700' },
  sub:            { color: 'rgba(255,255,255,0.7)', fontSize: Font.xs, marginTop: 4 },
  gwaRow:         {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    marginTop: 16,
  },
  gwaNum:         { color: '#fff', fontSize: 48, fontWeight: '800', lineHeight: 52 },
  gwaLabel:       { color: 'rgba(255,255,255,0.7)', fontSize: Font.xs, marginTop: 2 },
  gwaBadge:       {
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: Radius.full,
    paddingHorizontal: 14,
    paddingVertical: 8,
    marginBottom: 4,
  },
  gwaBadgeText:   { color: '#fff', fontSize: Font.xs, fontWeight: '600' },

  tabs:           {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: -16,
    gap: 8,
    marginBottom: 16,
  },
  tab:            {
    flex: 1,
    paddingVertical: 10,
    borderRadius: Radius.md,
    alignItems: 'center',
    backgroundColor: Colors.card,
    borderWidth: 1,
    borderColor: Colors.border,
    ...Shadow.card,
  },
  tabText:        { fontSize: Font.sm, color: Colors.textSub, fontWeight: '600' },
  tabTextActive:  { color: '#fff' },

  section:        { paddingHorizontal: 16, gap: 10 },

  subjectCard:    {
    flexDirection: 'row',
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    overflow: 'hidden',
    ...Shadow.card,
  },
  accentBar:      { width: 5 },
  subjectBody:    { flex: 1, padding: 14 },
  subjectTop:     { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  subjectName:    { fontSize: Font.sm, fontWeight: '600', color: Colors.text, flex: 1 },
  score:          { fontSize: Font.xl, fontWeight: '800' },
  barTrack:       { height: 5, backgroundColor: Colors.border, borderRadius: Radius.full, overflow: 'hidden', marginBottom: 6 },
  barFill:        { height: '100%', borderRadius: Radius.full },
  remark:         { fontSize: Font.xs, fontWeight: '500' },

  emptyCard:      {
    backgroundColor: Colors.card,
    borderRadius: Radius.lg,
    padding: 32,
    alignItems: 'center',
    ...Shadow.card,
  },
  emptyIcon:      { fontSize: 40, marginBottom: 10 },
  emptyText:      { fontSize: Font.sm, color: Colors.textMuted, textAlign: 'center' },
});

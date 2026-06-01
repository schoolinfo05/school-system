// @ts-nocheck
// app/(tabs)/subjects.tsx — Student: view enrolled subjects

import { useEffect, useState } from 'react';
import {
  View, Text, ScrollView, StyleSheet, ActivityIndicator,
  RefreshControl, Platform, StatusBar,
} from 'react-native';
import api from '../../src/api';

const C = {
  blue: '#378ADD', blueLight: '#E6F1FB',
  green: '#1D9E75', greenLight: '#E1F5EE',
  warning: '#BA7517', warningLight: '#FFF3CD',
  bg: '#F4F6F9', card: '#FFFFFF', border: '#EFEFEF',
  text: '#1A1A2E', sub: '#6B7280', muted: '#B0B7C3',
};
const HEADER_TOP = Platform.OS === 'android' ? (StatusBar.currentHeight ?? 24) + 10 : 52;

const DAY_COLORS = {
  MWF: { bg: C.blueLight,   text: C.blue    },
  TTH: { bg: C.greenLight,  text: C.green   },
  SAT: { bg: C.warningLight,text: C.warning },
};

export default function MySubjects() {
  const [data, setData]         = useState(null);
  const [loading, setLoading]   = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchSubjects = async () => {
    try {
      const res = await api.get('/my-subjects');
      setData(res.data);
    } catch (e) { console.log(e.message); }
    finally { setLoading(false); setRefreshing(false); }
  };

  useEffect(() => { fetchSubjects(); }, []);

  const totalUnits = data?.subjects?.reduce((sum, s) => sum + s.units_lec + s.units_lab, 0) ?? 0;

  return (
    <View style={s.container}>
      <View style={s.header}>
        <Text style={s.title}>My Subjects</Text>
        <Text style={s.subtitle}>
          {data?.sections?.[0]?.name ?? 'No section assigned yet'}
        </Text>
      </View>

      {loading ? (
        <View style={s.center}><ActivityIndicator size="large" color={C.blue} /></View>
      ) : (
        <ScrollView contentContainerStyle={s.body}
          refreshControl={<RefreshControl refreshing={refreshing}
            onRefresh={() => { setRefreshing(true); fetchSubjects(); }} />}>

          {/* Summary bar */}
          {data?.subjects?.length > 0 && (
            <View style={s.summaryBar}>
              <View style={s.summaryItem}>
                <Text style={s.summaryCount}>{data.subjects.length}</Text>
                <Text style={s.summaryLabel}>Subjects</Text>
              </View>
              <View style={s.summaryDivider} />
              <View style={s.summaryItem}>
                <Text style={s.summaryCount}>{totalUnits}</Text>
                <Text style={s.summaryLabel}>Total Units</Text>
              </View>
              <View style={s.summaryDivider} />
              <View style={s.summaryItem}>
                <Text style={s.summaryCount}>{data.sections?.length ?? 0}</Text>
                <Text style={s.summaryLabel}>Sections</Text>
              </View>
            </View>
          )}

          {/* Subject list */}
          {!data?.subjects?.length ? (
            <View style={s.emptyWrap}>
              <Text style={s.emptyIcon}>📚</Text>
              <Text style={s.emptyTitle}>No subjects yet</Text>
              <Text style={s.emptySub}>{"You haven't been assigned to a section yet."}{'\n'}Please contact the registrar.</Text>
            </View>
          ) : (
            data.subjects.map((sub, i) => {
              const dayColor = DAY_COLORS[sub.day] ?? { bg: C.bg, text: C.sub };
              return (
                <View key={i} style={s.subjectCard}>
                  <View style={s.subjectTop}>
                    <View style={s.codeTag}>
                      <Text style={s.codeText}>{sub.code}</Text>
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={s.subjectName}>{sub.name}</Text>
                      <Text style={s.sectionName}>📍 {sub.section_name}</Text>
                    </View>
                    <View style={s.unitsTag}>
                      <Text style={s.unitsText}>{sub.units_lec + sub.units_lab}</Text>
                      <Text style={s.unitsLabel}>units</Text>
                    </View>
                  </View>

                  <View style={s.subjectBottom}>
                    {/* Schedule */}
                    {sub.day && (
                      <View style={[s.dayBadge, { backgroundColor: dayColor.bg }]}>
                        <Text style={[s.dayText, { color: dayColor.text }]}>{sub.day}</Text>
                      </View>
                    )}
                    {sub.time_start && (
                      <Text style={s.schedText}>
                        🕐 {sub.time_start} – {sub.time_end}
                      </Text>
                    )}
                    {sub.room && (
                      <Text style={s.schedText}>🚪 {sub.room}</Text>
                    )}
                    {sub.teacher && (
                      <Text style={s.teacherText}>👨‍🏫 {sub.teacher}</Text>
                    )}
                  </View>

                  <View style={s.unitDetailRow}>
                    <Text style={s.unitDetailText}>Lec: {sub.units_lec} units</Text>
                    <Text style={s.unitDetailText}>Lab: {sub.units_lab} units</Text>
                  </View>
                </View>
              );
            })
          )}
          <View style={{ height: 24 }} />
        </ScrollView>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: C.bg },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header: { backgroundColor: C.blue, paddingTop: HEADER_TOP, paddingHorizontal: 16, paddingBottom: 16 },
  title: { color: '#fff', fontSize: 22, fontWeight: '700' },
  subtitle: { color: 'rgba(255,255,255,0.75)', fontSize: 12, marginTop: 2 },
  body: { padding: 12, gap: 10, paddingBottom: 24 },
  summaryBar: { flexDirection: 'row', backgroundColor: C.card, borderRadius: 14, padding: 16, marginBottom: 4, borderWidth: 1, borderColor: C.border },
  summaryItem: { flex: 1, alignItems: 'center' },
  summaryCount: { fontSize: 22, fontWeight: '800', color: C.blue },
  summaryLabel: { fontSize: 11, color: C.muted, marginTop: 2 },
  summaryDivider: { width: 1, backgroundColor: C.border, marginVertical: 4 },
  subjectCard: { backgroundColor: C.card, borderRadius: 14, padding: 14, borderWidth: 1, borderColor: C.border, elevation: 2 },
  subjectTop: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, marginBottom: 10 },
  codeTag: { backgroundColor: C.blueLight, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6, alignItems: 'center' },
  codeText: { color: C.blue, fontWeight: '800', fontSize: 12 },
  subjectName: { fontSize: 14, fontWeight: '700', color: C.text, lineHeight: 20 },
  sectionName: { fontSize: 11, color: C.muted, marginTop: 2 },
  unitsTag: { alignItems: 'center', backgroundColor: C.bg, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6 },
  unitsText: { fontSize: 18, fontWeight: '800', color: C.text },
  unitsLabel: { fontSize: 9, color: C.muted },
  subjectBottom: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, alignItems: 'center', marginBottom: 8 },
  dayBadge: { borderRadius: 6, paddingHorizontal: 8, paddingVertical: 4 },
  dayText: { fontSize: 11, fontWeight: '700' },
  schedText: { fontSize: 12, color: C.sub },
  teacherText: { fontSize: 12, color: C.sub },
  unitDetailRow: { flexDirection: 'row', gap: 12, paddingTop: 8, borderTopWidth: 1, borderTopColor: C.border },
  unitDetailText: { fontSize: 11, color: C.muted },
  emptyWrap: { alignItems: 'center', paddingVertical: 60 },
  emptyIcon: { fontSize: 52, marginBottom: 12 },
  emptyTitle: { fontSize: 16, fontWeight: '600', color: C.text },
  emptySub: { fontSize: 13, color: C.muted, marginTop: 4, textAlign: 'center', lineHeight: 20 },
});

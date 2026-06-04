// @ts-nocheck
// app/(tabs)/study-load.tsx - Student study load

import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import HeaderGradient from '../components/ui/HeaderGradient';
import api from '../../src/api';
import { useTheme } from '../../src/theme-context';

export default function StudyLoad() {
  const { theme } = useTheme();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadStudyLoad = async () => {
    try {
      const res = await api.get('/my-subjects');
      setData(res.data);
    } catch (error) {
      console.log('Study load error:', error.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadStudyLoad();
  }, []);

  const subjects = useMemo(() => data?.subjects ?? [], [data?.subjects]);
  const sections = data?.sections ?? [];

  const totals = useMemo(() => {
    return subjects.reduce((sum, subject) => {
      const lec = Number(subject.units_lec || 0);
      const lab = Number(subject.units_lab || 0);
      return {
        lec: sum.lec + lec,
        lab: sum.lab + lab,
        units: sum.units + lec + lab,
      };
    }, { lec: 0, lab: 0, units: 0 });
  }, [subjects]);

  return (
    <View style={[styles.container, { backgroundColor: theme.bg }]}>
      <HeaderGradient
        title="Study Load"
        subtitle={sections[0]?.name ?? 'Current enrolled subjects'}
        initials="SL"
        stats={[
          { label: 'Subjects', value: subjects.length, accent: '#FDE68A' },
          { label: 'Units', value: totals.units, accent: '#A7F3D0' },
          { label: 'Sections', value: sections.length, accent: '#C7D2FE' },
        ]}
      />

      {loading ? (
        <View style={[styles.center, { backgroundColor: theme.bg }]}>
          <ActivityIndicator size="large" color={theme.primary} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={styles.body}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                loadStudyLoad();
              }}
            />
          }
        >
          <View style={[styles.summaryCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
            <View style={styles.summaryItem}>
              <Text style={[styles.summaryValue, { color: theme.primary }]}>{totals.lec}</Text>
              <Text style={[styles.summaryLabel, { color: theme.textSub }]}>Lecture units</Text>
            </View>
            <View style={[styles.divider, { backgroundColor: theme.border }]} />
            <View style={styles.summaryItem}>
              <Text style={[styles.summaryValue, { color: theme.primary }]}>{totals.lab}</Text>
              <Text style={[styles.summaryLabel, { color: theme.textSub }]}>Lab units</Text>
            </View>
            <View style={[styles.divider, { backgroundColor: theme.border }]} />
            <View style={styles.summaryItem}>
              <Text style={[styles.summaryValue, { color: theme.primary }]}>{totals.units}</Text>
              <Text style={[styles.summaryLabel, { color: theme.textSub }]}>Total units</Text>
            </View>
          </View>

          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Enrolled Subjects</Text>
            <Text style={[styles.sectionMeta, { color: theme.textSub }]}>
              {subjects.length} subject{subjects.length === 1 ? '' : 's'}
            </Text>
          </View>

          {subjects.length === 0 ? (
            <View style={[styles.emptyCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
              <Text style={[styles.emptyTitle, { color: theme.text }]}>No study load yet</Text>
              <Text style={[styles.emptyText, { color: theme.textSub }]}>
                Your enrolled subjects will appear here after your section or direct enrollment is assigned.
              </Text>
            </View>
          ) : (
            subjects.map((subject, index) => {
              const units = Number(subject.units_lec || 0) + Number(subject.units_lab || 0);
              return (
                <View key={`${subject.subject_id}-${index}`} style={[styles.subjectCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <View style={styles.subjectTop}>
                    <View style={[styles.codePill, { backgroundColor: theme.primaryLight }]}>
                      <Text style={[styles.codeText, { color: theme.primary }]}>{subject.code || 'SUBJ'}</Text>
                    </View>
                    <View style={styles.subjectInfo}>
                      <Text style={[styles.subjectName, { color: theme.text }]}>{subject.name}</Text>
                      <Text style={[styles.subjectSection, { color: theme.textSub }]}>
                        {subject.section_name || 'No section'}
                      </Text>
                    </View>
                    <View style={[styles.unitsBox, { backgroundColor: theme.bg }]}>
                      <Text style={[styles.unitsValue, { color: theme.text }]}>{units}</Text>
                      <Text style={[styles.unitsLabel, { color: theme.textSub }]}>units</Text>
                    </View>
                  </View>

                  <View style={[styles.detailGrid, { borderTopColor: theme.border }]}>
                    <Detail label="Schedule" value={formatSchedule(subject)} theme={theme} />
                    <Detail label="Room" value={subject.room || 'TBA'} theme={theme} />
                    <Detail label="Teacher" value={subject.teacher || 'TBA'} theme={theme} />
                    <Detail label="Lec / Lab" value={`${subject.units_lec || 0} / ${subject.units_lab || 0}`} theme={theme} />
                  </View>
                </View>
              );
            })
          )}
        </ScrollView>
      )}
    </View>
  );
}

function Detail({ label, value, theme }) {
  return (
    <View style={styles.detailItem}>
      <Text style={[styles.detailLabel, { color: theme.textMuted }]}>{label}</Text>
      <Text style={[styles.detailValue, { color: theme.textSub }]} numberOfLines={2}>{value}</Text>
    </View>
  );
}

function formatSchedule(subject) {
  if (!subject.day && !subject.time_start && !subject.time_end) return 'TBA';
  const time = subject.time_start || subject.time_end
    ? `${subject.time_start || ''}${subject.time_end ? ` - ${subject.time_end}` : ''}`
    : 'Time TBA';
  return `${subject.day || 'Day TBA'} • ${time}`;
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  body: { padding: 16, paddingBottom: 110, gap: 12 },
  summaryCard: {
    flexDirection: 'row',
    borderRadius: 14,
    borderWidth: 1,
    padding: 16,
  },
  summaryItem: { flex: 1, alignItems: 'center' },
  summaryValue: { fontSize: 22, fontWeight: '900' },
  summaryLabel: { fontSize: 11, fontWeight: '700', marginTop: 4 },
  divider: { width: 1, marginVertical: 4 },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 4,
  },
  sectionTitle: { fontSize: 16, fontWeight: '900' },
  sectionMeta: { fontSize: 12, fontWeight: '700' },
  subjectCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 14,
  },
  subjectTop: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  codePill: {
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
    minWidth: 58,
    alignItems: 'center',
  },
  codeText: { fontSize: 12, fontWeight: '900' },
  subjectInfo: { flex: 1 },
  subjectName: { fontSize: 14, fontWeight: '900', lineHeight: 20 },
  subjectSection: { fontSize: 12, marginTop: 3, fontWeight: '600' },
  unitsBox: {
    minWidth: 54,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
    alignItems: 'center',
  },
  unitsValue: { fontSize: 18, fontWeight: '900' },
  unitsLabel: { fontSize: 10, fontWeight: '700' },
  detailGrid: {
    borderTopWidth: 1,
    marginTop: 12,
    paddingTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  detailItem: { width: '47%' },
  detailLabel: { fontSize: 10, fontWeight: '900', textTransform: 'uppercase' },
  detailValue: { fontSize: 12, fontWeight: '700', marginTop: 3, lineHeight: 17 },
  emptyCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 24,
    alignItems: 'center',
  },
  emptyTitle: { fontSize: 16, fontWeight: '900' },
  emptyText: { fontSize: 13, textAlign: 'center', lineHeight: 20, marginTop: 6 },
});

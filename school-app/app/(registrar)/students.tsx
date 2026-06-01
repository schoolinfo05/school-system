// @ts-nocheck
import { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, TextInput,
  TouchableOpacity, ActivityIndicator, RefreshControl,
  Platform, StatusBar, Modal,
} from 'react-native';
import api from '../../src/api';

const HEADER_TOP = Platform.OS === 'android'
  ? (StatusBar.currentHeight ?? 24) + 10 : 52;

const STATUS_CONFIG = {
  active:    { label: 'Active',    bg: '#E1F5EE', color: '#1D9E75' },
  inactive:  { label: 'Inactive',  bg: '#F8F0F2', color: '#D85A30' },
  graduated: { label: 'Graduated', bg: '#E8F1FF', color: '#378ADD' },
};

export default function RegistrarStudents() {
  const [students, setStudents]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch]         = useState('');
  const [selected, setSelected]     = useState(null);

  const fetchStudents = useCallback(async () => {
    try {
      const res = await api.get('/students');
      setStudents(res.data || []);
    } catch (e) {
      console.log('Students fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { fetchStudents(); }, [fetchStudents]);

  const filteredStudents = search.trim()
    ? students.filter(student => {
        const term = search.trim().toLowerCase();
        return [student.first_name, student.last_name, student.email, student.student_id, student.grade_level, student.section]
          .some(value => value?.toString().toLowerCase().includes(term));
      })
    : students;

  const openDetail = (student) => setSelected(student);

  return (
    <View style={s.container}>
      <View style={s.header}>
        <Text style={s.title}>Students</Text>
        <Text style={s.subtitle}>Review approved learners and student profiles.</Text>

        <View style={s.searchBox}>
          <Text style={s.searchIcon}>🔎</Text>
          <TextInput
            style={s.searchInput}
            placeholder="Search students by name, email, or ID"
            placeholderTextColor="rgba(0,0,0,0.35)"
            value={search}
            onChangeText={setSearch}
            returnKeyType="search"
          />
        </View>
      </View>

      {loading ? (
        <View style={s.center}>
          <ActivityIndicator size="large" color="#378ADD" />
        </View>
      ) : (
        <ScrollView
          style={s.list}
          contentContainerStyle={{ paddingBottom: 24 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchStudents(); }} />
          }
        >
          {filteredStudents.length === 0 ? (
            <View style={s.emptyWrap}>
              <Text style={s.emptyIcon}>🧑‍🎓</Text>
              <Text style={s.emptyTitle}>No students found</Text>
              <Text style={s.emptySub}>Try adjusting your search or refresh the list.</Text>
            </View>
          ) : filteredStudents.map((student, index) => {
            const status = STATUS_CONFIG[student.status] || STATUS_CONFIG.active;
            return (
              <TouchableOpacity
                key={index}
                style={s.studentCard}
                onPress={() => openDetail(student)}
                activeOpacity={0.8}
              >
                <View style={s.cardRow}>
                  <View style={s.avatar}>
                    <Text style={s.avatarText}>
                      {student.first_name?.[0]}{student.last_name?.[0]}
                    </Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={s.studentName}>{student.first_name} {student.last_name}</Text>
                    <Text style={s.studentMeta}>{student.student_id} · {student.email}</Text>
                    <Text style={s.studentProgram}>{student.grade_level} · {student.section} · {student.school_year}</Text>
                  </View>
                  <View style={[s.statusBadge, { backgroundColor: status.bg }]}> 
                    <Text style={[s.statusText, { color: status.color }]}>{status.label}</Text>
                  </View>
                </View>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      )}

      <Modal visible={!!selected} animationType="slide" transparent>
        <View style={s.modalOverlay}>
          <View style={s.modalCard}>
            <View style={s.modalHeader}>
              <Text style={s.modalTitle}>Student detail</Text>
              <TouchableOpacity onPress={() => setSelected(null)} style={s.modalClose}>
                <Text style={s.modalCloseText}>✕</Text>
              </TouchableOpacity>
            </View>
            {selected && (
              <View style={s.modalBody}>
                <Text style={s.modalLabel}>{selected.student_id}</Text>
                <Text style={s.modalName}>{selected.first_name} {selected.last_name}</Text>
                <View style={s.detailRow}><Text style={s.detailLabel}>Email</Text><Text style={s.detailValue}>{selected.email}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>Grade / Section</Text><Text style={s.detailValue}>{selected.grade_level} · {selected.section}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>School year</Text><Text style={s.detailValue}>{selected.school_year}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>Status</Text><Text style={s.detailValue}>{selected.status}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>Phone</Text><Text style={s.detailValue}>{selected.phone ?? '—'}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>Gender</Text><Text style={s.detailValue}>{selected.gender ?? '—'}</Text></View>
                <View style={s.detailRow}><Text style={s.detailLabel}>Address</Text><Text style={s.detailValue}>{selected.address ?? '—'}</Text></View>
              </View>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F4F6F9' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header: { backgroundColor: '#378ADD', paddingTop: HEADER_TOP, paddingHorizontal: 16, paddingBottom: 18 },
  title: { color: '#fff', fontSize: 22, fontWeight: '700' },
  subtitle: { color: 'rgba(255,255,255,0.85)', fontSize: 13, marginTop: 4, marginBottom: 12 },
  searchBox: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.18)', borderRadius: 12, padding: 12, gap: 8 },
  searchIcon: { fontSize: 14 },
  searchInput: { flex: 1, color: '#fff', fontSize: 13 },
  list: { flex: 1, paddingHorizontal: 12, paddingTop: 14 },
  emptyWrap: { alignItems: 'center', paddingVertical: 80 },
  emptyIcon: { fontSize: 42, marginBottom: 14 },
  emptyTitle: { fontSize: 17, fontWeight: '700', color: '#111827' },
  emptySub: { fontSize: 13, color: '#6B7280', marginTop: 6, textAlign: 'center', maxWidth: 280 },
  studentCard: { backgroundColor: '#fff', borderRadius: 14, padding: 14, marginBottom: 12, borderWidth: 1, borderColor: '#E5E7EB' },
  cardRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#E1F5EE', justifyContent: 'center', alignItems: 'center' },
  avatarText: { fontSize: 16, fontWeight: '700', color: '#1D9E75' },
  studentName: { fontSize: 15, fontWeight: '700', color: '#111827' },
  studentMeta: { fontSize: 12, color: '#6B7280', marginTop: 2 },
  studentProgram: { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
  statusBadge: { borderRadius: 16, paddingVertical: 6, paddingHorizontal: 12, alignSelf: 'flex-start' },
  statusText: { fontSize: 11, fontWeight: '700' },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.35)', justifyContent: 'flex-end' },
  modalCard: { backgroundColor: '#fff', borderTopLeftRadius: 18, borderTopRightRadius: 18, maxHeight: '76%', paddingBottom: 24 },
  modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 18, borderBottomWidth: 1, borderColor: '#E5E7EB' },
  modalTitle: { fontSize: 17, fontWeight: '700', color: '#111827' },
  modalClose: { width: 36, height: 36, borderRadius: 18, justifyContent: 'center', alignItems: 'center', backgroundColor: '#F3F4F6' },
  modalCloseText: { fontSize: 18, color: '#6B7280' },
  modalBody: { paddingHorizontal: 18, paddingTop: 12 },
  modalLabel: { textTransform: 'uppercase', color: '#6B7280', fontSize: 11, marginBottom: 4 },
  modalName: { fontSize: 22, fontWeight: '700', color: '#111827', marginBottom: 16 },
  detailRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 10, borderBottomWidth: 1, borderColor: '#F3F4F6' },
  detailLabel: { color: '#6B7280', fontSize: 12 },
  detailValue: { color: '#111827', fontSize: 13, fontWeight: '600', maxWidth: '55%', textAlign: 'right' },
});

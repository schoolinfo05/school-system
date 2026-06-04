// @ts-nocheck
// app/(tabs)/market.tsx — Marketplace screen with item image upload + detail modal

import { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, ActivityIndicator,
  TouchableOpacity, TextInput, RefreshControl, Alert, Modal,
  Platform, StatusBar, Image, Dimensions,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import HeaderGradient from '../components/ui/HeaderGradient';
import SearchBar from '../components/ui/SearchBar';
import api from '../../src/api';
import { useTheme } from '../../src/theme-context';

// ── Design tokens ──────────────────────────────────────────────
const C = {
  blue:        '#378ADD',
  blueLight:   '#E6F1FB',
  green:       '#1D9E75',
  greenLight:  '#E1F5EE',
  danger:      '#E24B4A',
  dangerLight: '#FCEBEB',
  warning:     '#BA7517',
  warningLight:'#FFF3CD',
  bg:          '#F4F6F9',
  card:        '#FFFFFF',
  border:      '#EFEFEF',
  text:        '#1A1A2E',
  sub:         '#6B7280',
  muted:       '#B0B7C3',
};

const SCREEN_W = Dimensions.get('window').width;

const HEADER_TOP = Platform.OS === 'android'
  ? (StatusBar.currentHeight ?? 24) + 10
  : 52;

const CATEGORIES = [
  { key: 'all',         label: 'All'         },
  { key: 'books',       label: 'Books'       },
  { key: 'uniforms',    label: 'Uniforms'    },
  { key: 'electronics', label: 'Electronics' },
  { key: 'supplies',    label: 'Supplies'    },
  { key: 'other',       label: 'Other'       },
];

const CAT_EMOJI = {
  all:         '🛒',
  books:       '📚',
  uniforms:    '👕',
  electronics: '📱',
  supplies:    '✏️',
  other:       '📦',
};

const CONDITION_MAP = {
  new:      { label: 'New',       bg: C.greenLight,   text: C.green   },
  like_new: { label: 'Like new',  bg: C.blueLight,    text: C.blue    },
  good:     { label: 'Good',      bg: C.warningLight, text: C.warning },
  fair:     { label: 'Fair',      bg: C.dangerLight,  text: C.danger  },
};

const STATUS_MAP = {
  available: { label: 'AVAILABLE', bg: C.green   },
  sold:      { label: 'SOLD',      bg: C.danger  },
  reserved:  { label: 'RESERVED',  bg: C.warning },
};

// ── Component ───────────────────────────────────────────────────
const SCHOOL_MANAGEMENT_ROLES = ['admin', 'registrar', 'school_management'];
const DEFAULT_PAYMENT_OPTIONS = {
  qrph: {
    enabled: true,
    account_name: 'School Marketplace',
    account_number: '',
    image_url: '',
    instructions: 'Scan the QRPH code with GCash, Maya, or your banking app, then enter the payment reference number.',
  },
};

export default function Market() {
  const { theme } = useTheme();
  const [role, setRole]             = useState(null);
  const [viewMode, setViewMode]     = useState('browse');
  const [items, setItems]           = useState([]);
  const [myItems, setMyItems]       = useState([]);
  const [orders, setOrders]         = useState([]);
  const [sales, setSales]           = useState([]);
  const [paymentOptions, setPaymentOptions] = useState(DEFAULT_PAYMENT_OPTIONS);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [category, setCategory]     = useState('all');
  const [search, setSearch]         = useState('');
  const [showSell, setShowSell]     = useState(false);
  const [posting, setPosting]       = useState(false);
  const [updatingId, setUpdatingId] = useState(null);
  const [buyingId, setBuyingId]     = useState(null);
  const [checkoutItem, setCheckoutItem] = useState(null);
  const [cancelOrder, setCancelOrder] = useState(null);
  const [cancelReason, setCancelReason] = useState('');
  const [cancellingId, setCancellingId] = useState(null);
  const [verifyingId, setVerifyingId] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState('gcash');
  const [paymentReference, setPaymentReference] = useState('');
  const [checkoutQuantity, setCheckoutQuantity] = useState('1');
  const [qrphImage, setQrphImage] = useState(null);

  // ── Item detail modal ────────────────────────────────────────
  const [viewItem, setViewItem]           = useState(null);
  const [galleryIndex, setGalleryIndex]   = useState(0);

  // ── Item image state ─────────────────────────────────────────
  const [itemImages, setItemImages]         = useState([]);
  const [editItemImages, setEditItemImages] = useState([]);

  // ── Edit modal state ─────────────────────────────────────────
  const [showEdit, setShowEdit]           = useState(false);
  const [editItem, setEditItem]           = useState(null);
  const [editForm, setEditForm]           = useState(null);
  const [editQrphImage, setEditQrphImage] = useState(null);
  const [saving, setSaving]               = useState(false);

  const [form, setForm] = useState({
    title: '', description: '', price: '', stock: '1',
    category: 'books', condition: 'good', location: 'Cebu City',
    accepts_cash: true, accepts_gcash: true, accepts_qrph: true,
    gcash_name: '', gcash_number: '', qrph_image_url: '',
  });
  const canManageListings = SCHOOL_MANAGEMENT_ROLES.includes(role);
  const canBuyItems = role === 'student';

  useEffect(() => {
    AsyncStorage.getItem('role').then(setRole);
    api.get('/marketplace/payment-options')
      .then(res => setPaymentOptions({
        ...DEFAULT_PAYMENT_OPTIONS,
        ...res.data,
        qrph: { ...DEFAULT_PAYMENT_OPTIONS.qrph, ...(res.data?.qrph ?? {}) },
      }))
      .catch(e => console.log('Payment options error:', e.message));
  }, []);

  useEffect(() => {
    if (role && !canManageListings && viewMode === 'mine') setViewMode('browse');
    if (role && !canManageListings && viewMode === 'sales') setViewMode('browse');
    if (role && !canBuyItems && viewMode === 'orders') setViewMode('browse');
  }, [role, canManageListings, canBuyItems, viewMode]);

  // ── Data fetching ───────────────────────────────────────────
  const fetchItems = useCallback(async () => {
    try {
      const params = {};
      if (category !== 'all') params.category = category;
      if (search.trim()) params.search = search.trim();
      const res = await api.get('/marketplace', { params });
      setItems(res.data);
    } catch (e) {
      console.log('Market fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [category, search]);

  const fetchMyItems = useCallback(async () => {
    try {
      const res = await api.get('/marketplace/my-items');
      setMyItems(res.data);
    } catch (e) {
      console.log('My items fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const fetchMyOrders = useCallback(async () => {
    try {
      const res = await api.get('/marketplace/my-orders');
      setOrders(res.data);
    } catch (e) {
      console.log('Orders fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const fetchSales = useCallback(async () => {
    try {
      const res = await api.get('/marketplace/sales');
      setSales(res.data);
    } catch (e) {
      console.log('Sales fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { fetchItems(); }, [fetchItems]);

  useEffect(() => {
    setLoading(true);
    if (viewMode === 'mine') fetchMyItems();
    else if (viewMode === 'orders') fetchMyOrders();
    else if (viewMode === 'sales') fetchSales();
    else fetchItems();
  }, [viewMode, fetchItems, fetchMyItems, fetchMyOrders, fetchSales]);

  // ── Image pickers ───────────────────────────────────────────
  const pickItemImages = async (setter) => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Permission required', 'Allow photo access to upload item images.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: true,
      selectionLimit: 3,
      allowsEditing: false,
      quality: 0.85,
    });
    if (!result.canceled && result.assets?.length) {
      setter(result.assets.slice(0, 3));
    }
  };

  const pickQrphImage = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Permission required', 'Allow photo access to upload the QRPH image.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: false,
      quality: 0.9,
    });
    if (!result.canceled && result.assets?.[0]) {
      setQrphImage(result.assets[0]);
      setForm(p => ({ ...p, qrph_image_url: '' }));
    }
  };

  // ── Post item ───────────────────────────────────────────────
  const handleSell = async () => {
    if (!canManageListings) {
      Alert.alert('Not allowed', 'Only school management can post marketplace items.');
      setShowSell(false);
      return;
    }
    if (!form.title.trim() || !form.description.trim() || !form.price || !form.stock) {
      Alert.alert('Missing info', 'Please fill in title, description, price, and stock.');
      return;
    }
    if (!form.accepts_cash && !form.accepts_gcash && !form.accepts_qrph) {
      Alert.alert('Payment required', 'Select at least one payment method.');
      return;
    }
    if (form.accepts_gcash && (!form.gcash_name.trim() || !form.gcash_number.trim())) {
      Alert.alert('GCash details required', 'Enter the GCash account name and number for online payment.');
      return;
    }
    if (form.accepts_qrph && !qrphImage && !form.qrph_image_url.trim()) {
      Alert.alert('QRPH image required', 'Please upload the QRPH image for this item.');
      return;
    }

    setPosting(true);
    try {
      const payload = new FormData();
      Object.entries({
        ...form,
        price:         String(parseFloat(form.price)),
        stock:         String(parseInt(form.stock, 10)),
        accepts_cash:  form.accepts_cash  ? '1' : '0',
        accepts_gcash: form.accepts_gcash ? '1' : '0',
        accepts_qrph:  form.accepts_qrph  ? '1' : '0',
        gcash_name:    form.accepts_gcash ? form.gcash_name.trim()   : '',
        gcash_number:  form.accepts_gcash ? form.gcash_number.trim() : '',
      }).forEach(([key, value]) => payload.append(key, value ?? ''));

      if (qrphImage) {
        payload.append('qrph_image', {
          uri:  qrphImage.uri,
          name: qrphImage.fileName || 'qrph.jpg',
          type: qrphImage.mimeType || 'image/jpeg',
        });
      }

      itemImages.forEach((img, idx) => {
        payload.append('item_images[]', {
          uri:  img.uri,
          name: img.fileName || `item-${idx}.jpg`,
          type: img.mimeType || 'image/jpeg',
        });
      });

      await api.post('/marketplace', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setShowSell(false);
      setForm({
        title: '', description: '', price: '', stock: '1',
        category: 'books', condition: 'good', location: 'Cebu City',
        accepts_cash: true, accepts_gcash: true, accepts_qrph: true,
        gcash_name: '', gcash_number: '', qrph_image_url: '',
      });
      setItemImages([]);
      setQrphImage(null);
      if (viewMode === 'mine') fetchMyItems(); else fetchItems();
      Alert.alert('Listed!', 'Your item has been posted.');
    } catch {
      Alert.alert('Error', 'Could not post item. Please try again.');
    } finally {
      setPosting(false);
    }
  };

  // ── Edit item ───────────────────────────────────────────────
  const openEdit = (item) => {
    setEditItem(item);
    setEditForm({
      title:          item.title          ?? '',
      description:    item.description    ?? '',
      price:          String(item.price   ?? ''),
      stock:          String(item.stock   ?? '1'),
      category:       item.category       ?? 'books',
      condition:      item.condition      ?? 'good',
      location:       item.location       ?? '',
      accepts_cash:   !!item.accepts_cash,
      accepts_gcash:  !!item.accepts_gcash,
      accepts_qrph:   !!item.accepts_qrph,
      gcash_name:     item.gcash_name     ?? '',
      gcash_number:   item.gcash_number   ?? '',
      qrph_image_url: item.qrph_image_url ?? '',
    });
    setEditQrphImage(null);
    setEditItemImages([]);
    setShowEdit(true);
  };

  const pickEditQrphImage = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Permission required', 'Allow photo access to upload the QRPH image.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: false,
      quality: 0.9,
    });
    if (!result.canceled && result.assets?.[0]) {
      setEditQrphImage(result.assets[0]);
      setEditForm(p => ({ ...p, qrph_image_url: '' }));
    }
  };

  const handleEdit = async () => {
    if (!editItem || !editForm) return;
    if (!editForm.title.trim() || !editForm.description.trim() || !editForm.price || !editForm.stock) {
      Alert.alert('Missing info', 'Please fill in title, description, price, and stock.');
      return;
    }
    if (!editForm.accepts_cash && !editForm.accepts_gcash && !editForm.accepts_qrph) {
      Alert.alert('Payment required', 'Select at least one payment method.');
      return;
    }
    if (editForm.accepts_gcash && (!editForm.gcash_name.trim() || !editForm.gcash_number.trim())) {
      Alert.alert('GCash details required', 'Enter the GCash account name and number.');
      return;
    }
    if (editForm.accepts_qrph && !editQrphImage && !editForm.qrph_image_url.trim()) {
      Alert.alert('QRPH image required', 'Please upload the QRPH image.');
      return;
    }

    setSaving(true);
    try {
      const payload = new FormData();
      Object.entries({
        ...editForm,
        price:         String(parseFloat(editForm.price)),
        stock:         String(parseInt(editForm.stock, 10)),
        accepts_cash:  editForm.accepts_cash  ? '1' : '0',
        accepts_gcash: editForm.accepts_gcash ? '1' : '0',
        accepts_qrph:  editForm.accepts_qrph  ? '1' : '0',
        gcash_name:    editForm.accepts_gcash ? editForm.gcash_name.trim()   : '',
        gcash_number:  editForm.accepts_gcash ? editForm.gcash_number.trim() : '',
      }).forEach(([key, value]) => payload.append(key, value ?? ''));

      if (editQrphImage) {
        payload.append('qrph_image', {
          uri:  editQrphImage.uri,
          name: editQrphImage.fileName || 'qrph.jpg',
          type: editQrphImage.mimeType || 'image/jpeg',
        });
      }

      editItemImages.forEach((img, idx) => {
        payload.append('item_images[]', {
          uri:  img.uri,
          name: img.fileName || `item-${idx}.jpg`,
          type: img.mimeType || 'image/jpeg',
        });
      });

      payload.append('_method', 'PUT');

      const res = await api.post(`/marketplace/${editItem.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      const updated = res.data;
      setMyItems(prev => prev.map(i => i.id === updated.id ? updated : i));
      setItems(prev   => prev.map(i => i.id === updated.id ? updated : i));
      setShowEdit(false);
      setEditItem(null);
      setEditItemImages([]);
      Alert.alert('Updated!', 'Your listing has been updated.');
    } catch {
      Alert.alert('Error', 'Could not update item. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  // ── Update status ───────────────────────────────────────────
  const handleUpdateStatus = async (item, newStatus) => {
    setUpdatingId(item.id);
    try {
      await api.put(`/marketplace/${item.id}`, { status: newStatus });
      setMyItems(prev =>
        prev.map(i => i.id === item.id ? { ...i, status: newStatus } : i)
      );
    } catch {
      Alert.alert('Error', 'Could not update status. Please try again.');
    } finally {
      setUpdatingId(null);
    }
  };

  // ── Delete ──────────────────────────────────────────────────
  const handleDelete = (item) => {
    Alert.alert(
      'Delete listing',
      `Remove "${item.title}" from the marketplace?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            setUpdatingId(item.id);
            try {
              await api.delete(`/marketplace/${item.id}`);
              setMyItems(prev => prev.filter(i => i.id !== item.id));
            } catch {
              Alert.alert('Error', 'Could not delete item. Please try again.');
            } finally {
              setUpdatingId(null);
            }
          },
        },
      ]
    );
  };

  const openCheckout = (item) => {
    const defaultMethod = item.accepts_qrph && paymentOptions?.qrph ? 'qrph' : item.accepts_gcash ? 'gcash' : 'cash';
    setCheckoutItem(item);
    setPaymentMethod(defaultMethod);
    setPaymentReference('');
    setCheckoutQuantity('1');
  };

  const handleBuy = async () => {
    if (!checkoutItem) return;
    const quantity = parseInt(checkoutQuantity, 10);
    if (!quantity || quantity < 1) {
      Alert.alert('Quantity required', 'Enter how many items you want to buy.');
      return;
    }
    if (quantity > (checkoutItem.stock ?? 1)) {
      Alert.alert('Not enough stock', `Only ${checkoutItem.stock ?? 1} item(s) are available.`);
      return;
    }
    if (['gcash', 'qrph'].includes(paymentMethod) && paymentReference.trim().length < 3) {
      Alert.alert('Reference required', 'Enter the payment reference number after sending payment.');
      return;
    }

    setBuyingId(checkoutItem.id);
    try {
      const res = await api.post(`/marketplace/${checkoutItem.id}/buy`, {
        payment_method:   paymentMethod,
        quantity,
        gcash_reference: ['gcash', 'qrph'].includes(paymentMethod) ? paymentReference.trim() : null,
      });
      const updated = res.data.item;
      const order   = res.data.order;
      setItems(prev =>
        updated.stock > 0
          ? prev.map(i => i.id === updated.id ? updated : i)
          : prev.filter(i => i.id !== updated.id)
      );
      setOrders(prev => [order, ...prev]);
      setCheckoutItem(null);
      setPaymentReference('');
      Alert.alert(
        ['gcash', 'qrph'].includes(paymentMethod) ? 'Pending verification' : 'Checkout started',
        ['gcash', 'qrph'].includes(paymentMethod)
          ? 'Your payment reference was submitted. School management will verify the payment.'
          : 'The seller has been notified. Please coordinate payment and pickup with them.'
      );
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message ?? 'Could not complete checkout for this item.');
    } finally {
      setBuyingId(null);
    }
  };

  const openCancelOrder = (order) => {
    setCancelOrder(order);
    setCancelReason('');
  };

  const handleCancelOrder = async () => {
    if (!cancelOrder) return;
    if (cancelReason.trim().length < 3) {
      Alert.alert('Reason required', 'Please enter why you want to cancel this checkout.');
      return;
    }

    setCancellingId(cancelOrder.id);
    try {
      const res = await api.post(`/marketplace/orders/${cancelOrder.id}/cancel`, {
        reason: cancelReason.trim(),
      });
      const updatedOrder = res.data;
      setOrders(prev => prev.map(order => order.id === updatedOrder.id ? updatedOrder : order));
      setCancelOrder(null);
      setCancelReason('');
      if (viewMode === 'browse') fetchItems();
      Alert.alert('Cancelled', 'Your checkout was cancelled and the seller was notified.');
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message ?? 'Could not cancel this checkout.');
    } finally {
      setCancellingId(null);
    }
  };

  const handleMarkPaid = async (order) => {
    setVerifyingId(order.id);
    try {
      const res = await api.post(`/marketplace/orders/${order.id}/mark-paid`);
      const updatedOrder = res.data;
      setSales(prev => prev.map(item => item.id === updatedOrder.id ? updatedOrder : item));
      Alert.alert('Verified', 'Order payment was marked as paid.');
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message ?? 'Could not verify this payment.');
    } finally {
      setVerifyingId(null);
    }
  };

  // ── Open item detail ────────────────────────────────────────
  const openItemDetail = (item) => {
    setViewItem(item);
    setGalleryIndex(0);
  };

  const renderOrderCard = (order) => {
    const item        = order.item ?? {};
    const isGcash     = order.payment_method === 'gcash';
    const isQrph      = order.payment_method === 'qrph';
    const isCancelled = order.status === 'cancelled';
    const isCompleted = order.status === 'completed';
    const canCancel   = !isCancelled && !isCompleted;

    return (
      <View key={order.id} style={s.orderCard}>
        <View style={s.orderTop}>
          <TouchableOpacity style={s.orderIcon} onPress={() => item.id && openItemDetail(item)} activeOpacity={0.8}>
            {item.image_urls?.[0] ? (
              <Image source={{ uri: item.image_urls[0] }} style={s.orderThumb} resizeMode="cover" />
            ) : (
              <Text style={s.orderIconText}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
            )}
          </TouchableOpacity>
          <View style={{ flex: 1 }}>
            <Text style={s.orderTitle}>{item.title ?? 'Marketplace item'}</Text>
            <Text style={s.orderSeller}>Seller: {order.seller?.name ?? item.seller?.name ?? 'School Marketplace'}</Text>
          </View>
          <View style={[
            s.orderStatus,
            isCancelled ? s.orderStatusCancelled : isGcash ? s.orderStatusPaid : s.orderStatusReserved,
          ]}>
            <Text style={[
              s.orderStatusText,
              isCancelled ? s.orderStatusCancelledText : isGcash ? s.orderStatusPaidText : s.orderStatusReservedText,
            ]}>
              {isCancelled ? 'CANCELLED' : order.status === 'paid' ? 'PAID' : ['gcash', 'qrph'].includes(order.payment_method) ? 'PENDING' : 'CHECKOUT'}
            </Text>
          </View>
        </View>

        <View style={s.orderMetaGrid}>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Amount</Text>
            <Text style={s.orderMetaValue}>₱{Number(order.total_amount ?? 0).toLocaleString()}</Text>
          </View>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Payment</Text>
            <Text style={s.orderMetaValue}>{isQrph ? 'QRPH' : isGcash ? 'GCash' : 'Cash'}</Text>
          </View>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Qty</Text>
            <Text style={s.orderMetaValue}>{order.quantity ?? 1}</Text>
          </View>
        </View>

        {isCancelled && order.notes ? (
          <View style={s.cancelReasonBox}>
            <Text style={s.referenceLabel}>Cancel reason</Text>
            <Text style={s.orderNote}>{order.notes}</Text>
          </View>
        ) : ['gcash', 'qrph'].includes(order.payment_method) && order.gcash_reference ? (
          <View style={s.referenceBox}>
            <Text style={s.referenceLabel}>{isQrph ? 'QRPH reference' : 'GCash reference'}</Text>
            <Text style={s.referenceValue}>{order.gcash_reference}</Text>
          </View>
        ) : ['gcash', 'qrph'].includes(order.payment_method) ? (
          <Text style={s.orderNote}>Payment is pending school management verification.</Text>
        ) : (
          <Text style={s.orderNote}>Coordinate pickup and payment with the seller.</Text>
        )}

        {canCancel && (
          <TouchableOpacity
            style={[s.orderCancelBtn, cancellingId === order.id && { opacity: 0.6 }]}
            onPress={() => openCancelOrder(order)}
            disabled={cancellingId === order.id}
          >
            <Text style={s.orderCancelText}>Cancel checkout</Text>
          </TouchableOpacity>
        )}
      </View>
    );
  };

  const renderSaleCard = (order) => {
    const item       = order.item ?? {};
    const buyerName  = order.buyer?.name ?? 'Student buyer';
    const status     = order.status ?? 'reserved';
    const isCancelled= status === 'cancelled';
    const isPaid     = status === 'paid';
    const canVerify  = ['pending_verification', 'reserved'].includes(status) && ['gcash', 'qrph'].includes(order.payment_method);

    return (
      <View key={order.id} style={s.orderCard}>
        <View style={s.orderTop}>
          <TouchableOpacity style={s.orderIcon} onPress={() => item.id && openItemDetail(item)} activeOpacity={0.8}>
            {item.image_urls?.[0] ? (
              <Image source={{ uri: item.image_urls[0] }} style={s.orderThumb} resizeMode="cover" />
            ) : (
              <Text style={s.orderIconText}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
            )}
          </TouchableOpacity>
          <View style={{ flex: 1 }}>
            <Text style={s.orderTitle}>{item.title ?? 'Marketplace item'}</Text>
            <Text style={s.orderSeller}>Buyer: {buyerName}</Text>
          </View>
          <View style={[
            s.orderStatus,
            isCancelled ? s.orderStatusCancelled : isPaid ? s.orderStatusPaid : s.orderStatusReserved,
          ]}>
            <Text style={[
              s.orderStatusText,
              isCancelled ? s.orderStatusCancelledText : isPaid ? s.orderStatusPaidText : s.orderStatusReservedText,
            ]}>
              {status.toUpperCase()}
            </Text>
          </View>
        </View>

        <View style={s.orderMetaGrid}>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Amount</Text>
            <Text style={s.orderMetaValue}>₱{Number(order.total_amount ?? 0).toLocaleString()}</Text>
          </View>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Payment</Text>
            <Text style={s.orderMetaValue}>{order.payment_method === 'qrph' ? 'QRPH' : order.payment_method === 'gcash' ? 'GCash' : 'Cash'}</Text>
          </View>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Qty</Text>
            <Text style={s.orderMetaValue}>{order.quantity ?? 1}</Text>
          </View>
          <View style={s.orderMetaItem}>
            <Text style={s.orderMetaLabel}>Stock left</Text>
            <Text style={s.orderMetaValue}>{item.stock ?? 0}</Text>
          </View>
        </View>

        {['gcash', 'qrph'].includes(order.payment_method) ? (
          <View style={s.referenceBox}>
            <Text style={s.referenceLabel}>{order.payment_method === 'qrph' ? 'QRPH reference' : 'GCash reference'}</Text>
            <Text style={s.referenceValue}>{order.gcash_reference}</Text>
          </View>
        ) : null}

        {isCancelled && order.notes ? (
          <View style={s.cancelReasonBox}>
            <Text style={s.referenceLabel}>Cancel reason</Text>
            <Text style={s.orderNote}>{order.notes}</Text>
          </View>
        ) : null}

        {canVerify && (
          <TouchableOpacity
            style={[s.verifyBtn, verifyingId === order.id && { opacity: 0.6 }]}
            onPress={() => handleMarkPaid(order)}
            disabled={verifyingId === order.id}
          >
            {verifyingId === order.id
              ? <ActivityIndicator color="#fff" />
              : <Text style={s.verifyBtnText}>Mark as paid</Text>
            }
          </TouchableOpacity>
        )}
      </View>
    );
  };

  // ── Render card ─────────────────────────────────────────────
  const renderCard = (item, i) => {
    const cond          = CONDITION_MAP[item.condition] ?? CONDITION_MAP.good;
    const status        = STATUS_MAP[item.status]       ?? STATUS_MAP.available;
    const isSold        = item.status === 'sold';
    const isReserved    = item.status === 'reserved';
    const isUnavailable = isSold || isReserved;
    const isUpdating    = updatingId === item.id;
    const isBuying      = buyingId === item.id;
    const isMine        = viewMode === 'mine';
    const firstImage    = item.image_urls?.[0] ?? null;

    return (
      <View key={i} style={[s.itemCard, isUnavailable && !isMine && s.itemCardDimmed]}>

        {/* Tappable image area → opens detail modal */}
        <TouchableOpacity
          style={s.itemImg}
          onPress={() => openItemDetail(item)}
          activeOpacity={0.88}
        >
          {firstImage ? (
            <Image
              source={{ uri: firstImage }}
              style={{ width: '100%', height: '100%' }}
              resizeMode="cover"
            />
          ) : (
            <Text style={s.itemImgEmoji}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
          )}
          <View style={[s.statusBadge, { backgroundColor: status.bg }]}>
            <Text style={s.statusBadgeText}>{status.label}</Text>
          </View>
          {item.image_urls?.length > 1 && (
            <View style={s.photoCountBadge}>
              <Text style={s.photoCountText}>+{item.image_urls.length - 1}</Text>
            </View>
          )}
        </TouchableOpacity>

        {/* Body */}
        <View style={s.itemBody}>
          <TouchableOpacity onPress={() => openItemDetail(item)} activeOpacity={0.7}>
            <Text
              style={[s.itemTitle, isUnavailable && !isMine && s.textDimmed]}
              numberOfLines={2}
            >
              {item.title}
            </Text>
          </TouchableOpacity>

          <Text style={[s.itemPrice, isSold && !isMine && s.itemPriceSold]}>
            ₱{Number(item.price).toLocaleString()}
          </Text>

          <View style={s.itemMeta}>
            <View style={[s.condBadge, { backgroundColor: cond.bg }]}>
              <Text style={[s.condText, { color: cond.text }]}>{cond.label}</Text>
            </View>
            {!isMine && (
              <Text style={s.sellerName} numberOfLines={1}>
                {item.seller?.name?.split(' ')[0]}
              </Text>
            )}
          </View>

          {item.location ? (
            <Text style={s.itemLocation} numberOfLines={1}>
              📍 {item.location}
            </Text>
          ) : null}
          <Text style={s.stockText}>{item.stock ?? 1} in stock</Text>
          <View style={s.paymentRow}>
            {item.accepts_qrph  ? <Text style={s.paymentBadge}>QRPH</Text>  : null}
            {item.accepts_gcash ? <Text style={s.paymentBadge}>GCash</Text> : null}
            {item.accepts_cash  ? <Text style={s.paymentBadge}>Cash</Text>  : null}
          </View>

          {/* ── My Listings: action buttons ── */}
          {isMine ? (
            <View style={s.actionRow}>
              {isUpdating ? (
                <ActivityIndicator size="small" color={C.blue} style={{ marginVertical: 8 }} />
              ) : (
                <>
                  {isUnavailable && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnGreen]}
                      onPress={() => handleUpdateStatus(item, 'available')}
                    >
                      <Text style={[s.actionBtnText, { color: C.green }]}>✅ Relist</Text>
                    </TouchableOpacity>
                  )}
                  {!isSold && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnDanger]}
                      onPress={() => handleUpdateStatus(item, 'sold')}
                    >
                      <Text style={[s.actionBtnText, { color: C.danger }]}>🚫 Mark Sold</Text>
                    </TouchableOpacity>
                  )}
                  {!isReserved && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnWarning]}
                      onPress={() => handleUpdateStatus(item, 'reserved')}
                    >
                      <Text style={[s.actionBtnText, { color: C.warning }]}>🔒 Reserve</Text>
                    </TouchableOpacity>
                  )}
                  <TouchableOpacity
                    style={[s.actionBtn, s.actionBtnEdit]}
                    onPress={() => openEdit(item)}
                  >
                    <Text style={[s.actionBtnText, { color: C.blue }]}>✏️ Edit</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[s.actionBtn, s.actionBtnDelete]}
                    onPress={() => handleDelete(item)}
                  >
                    <Text style={[s.actionBtnText, { color: C.muted }]}>🗑 Delete</Text>
                  </TouchableOpacity>
                </>
              )}
            </View>
          ) : canBuyItems ? (
            <TouchableOpacity
              style={[s.buyBtn, (isBuying || isUnavailable) && { opacity: 0.6 }]}
              onPress={() => !isUnavailable && openCheckout(item)}
              disabled={isBuying || isUnavailable}
            >
              {isBuying
                ? <ActivityIndicator size="small" color="#fff" />
                : <Text style={s.buyBtnText}>{isUnavailable ? 'Unavailable' : 'Checkout'}</Text>
              }
            </TouchableOpacity>
          ) : null}
        </View>
      </View>
    );
  };

  // ── Render ───────────────────────────────────────────────────
  return (
    <View style={[s.container, { backgroundColor: theme.bg }]}>
      <HeaderGradient
        title="Marketplace"
        subtitle={
          viewMode === 'mine'
            ? 'Manage your listings'
            : viewMode === 'sales'
            ? 'Track buyers, payments, and stock'
            : viewMode === 'orders'
            ? 'Track reserved items and checkout payments'
            : 'Browse fixed-price school marketplace items'
        }
        initials="MK"
        stats={[
          { label: 'Browse', value: items.length, accent: '#A5F3FC' },
          { label: 'Sales', value: viewMode === 'sales' ? sales.length : 0, accent: '#FCD34D' },
          { label: 'Orders', value: viewMode === 'orders' ? orders.length : 0, accent: '#FCA5A5' },
        ]}
      >
        <SearchBar
          value={search}
          onChangeText={setSearch}
          placeholder="Search items..."
          onSubmitEditing={fetchItems}
        />
      </HeaderGradient>
      <View style={s.toggleRow}>
          <TouchableOpacity
            style={[s.toggleBtn, viewMode === 'browse' && s.toggleBtnActive]}
            onPress={() => setViewMode('browse')}
          >
            <Text style={[s.toggleBtnText, viewMode === 'browse' && s.toggleBtnTextActive]}>
              🛒  Browse
            </Text>
          </TouchableOpacity>
          {canManageListings && (
            <TouchableOpacity
              style={[s.toggleBtn, viewMode === 'mine' && s.toggleBtnActive]}
              onPress={() => setViewMode('mine')}
            >
              <Text style={[s.toggleBtnText, viewMode === 'mine' && s.toggleBtnTextActive]}>
                📋  My Listings
              </Text>
            </TouchableOpacity>
          )}
          {canManageListings && (
            <TouchableOpacity
              style={[s.toggleBtn, viewMode === 'sales' && s.toggleBtnActive]}
              onPress={() => setViewMode('sales')}
            >
              <Text style={[s.toggleBtnText, viewMode === 'sales' && s.toggleBtnTextActive]}>
                Sales
              </Text>
            </TouchableOpacity>
          )}
          {canBuyItems && (
            <TouchableOpacity
              style={[s.toggleBtn, viewMode === 'orders' && s.toggleBtnActive]}
              onPress={() => setViewMode('orders')}
            >
              <Text style={[s.toggleBtnText, viewMode === 'orders' && s.toggleBtnTextActive]}>
                Checkout
              </Text>
            </TouchableOpacity>
          )}
        </View>

      {/* ── Category tabs ── */}
      {viewMode === 'browse' && (
        <View style={s.catWrapper}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={s.catScroll}
          >
            {CATEGORIES.map(cat => {
              const active = category === cat.key;
              return (
                <TouchableOpacity
                  key={cat.key}
                  style={[s.catTab, active && s.catTabActive]}
                  onPress={() => setCategory(cat.key)}
                  activeOpacity={0.7}
                >
                  <Text style={s.catEmoji}>{CAT_EMOJI[cat.key]}</Text>
                  <Text style={[s.catLabel, active && s.catLabelActive]}>
                    {cat.label}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>
      )}

      {/* ── My Listings summary bar ── */}
      {viewMode === 'mine' && !loading && myItems.length > 0 && (
        <View style={s.summaryBar}>
          <Text style={s.summaryItem}>
            <Text style={s.summaryCount}>
              {myItems.filter(i => i.status === 'available').length}
            </Text>
            {'  available'}
          </Text>
          <Text style={s.summaryDivider}>·</Text>
          <Text style={s.summaryItem}>
            <Text style={[s.summaryCount, { color: theme.danger }]}>
              {myItems.filter(i => i.status === 'sold').length}
            </Text>
            {'  sold'}
          </Text>
          <Text style={s.summaryDivider}>·</Text>
          <Text style={s.summaryItem}>
            <Text style={[s.summaryCount, { color: C.warning }]}>
              {myItems.filter(i => i.status === 'reserved').length}
            </Text>
            {'  reserved'}
          </Text>
        </View>
      )}

      {/* ── Items grid ── */}
      {loading ? (
        <View style={[s.center, { backgroundColor: theme.bg }]}> 
          <ActivityIndicator size="large" color={theme.primary} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={s.grid}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                if (viewMode === 'mine') fetchMyItems();
                else if (viewMode === 'orders') fetchMyOrders();
                else if (viewMode === 'sales') fetchSales();
                else fetchItems();
              }}
            />
          }
        >
          {(viewMode === 'mine' ? myItems : viewMode === 'orders' ? orders : viewMode === 'sales' ? sales : items).length === 0 ? (
            <View style={s.emptyWrap}>
              <Text style={s.emptyIcon}>{viewMode === 'mine' ? '📋' : viewMode === 'orders' ? '🧾' : viewMode === 'sales' ? '₱' : '🛒'}</Text>
              <Text style={s.emptyTitle}>
                {viewMode === 'mine' ? 'No listings yet' : viewMode === 'orders' ? 'No checkout items yet' : viewMode === 'sales' ? 'No sales yet' : 'No items found'}
              </Text>
              <Text style={s.emptySub}>
                {viewMode === 'mine'
                  ? 'Tap "+ Sell" to post your first item.'
                  : viewMode === 'orders'
                  ? 'Reserved and paid marketplace items will appear here.'
                  : viewMode === 'sales'
                  ? 'Student checkouts will appear here with buyer and payment details.'
                  : canManageListings
                  ? 'Be the first to post something!'
                  : 'No marketplace items are available right now.'}
              </Text>
              {canManageListings && !['orders', 'sales'].includes(viewMode) && (
                <TouchableOpacity style={s.emptyBtn} onPress={() => setShowSell(true)}>
                  <Text style={s.emptyBtnText}>+ Post an item</Text>
                </TouchableOpacity>
              )}
            </View>
          ) : viewMode === 'sales' ? (
            sales.map(order => renderSaleCard(order))
          ) : viewMode === 'orders' ? (
            orders.map(order => renderOrderCard(order))
          ) : (
            (viewMode === 'mine' ? myItems : items).map((item, i) => renderCard(item, i))
          )}
        </ScrollView>
      )}

      {/* ══════════════════════════════════════════════════════
          ── Item Detail Modal ──
      ══════════════════════════════════════════════════════ */}
      <Modal visible={!!viewItem} animationType="slide" presentationStyle="pageSheet">
        <View style={s.modal}>
          {/* Header */}
          <View style={s.modalHeader}>
            <Text style={s.modalTitle} numberOfLines={1}>{viewItem?.title}</Text>
            <TouchableOpacity style={s.modalCloseBtn} onPress={() => setViewItem(null)}>
              <Text style={s.modalCloseText}>✕</Text>
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false}>

            {/* ── Image gallery (horizontal pager) ── */}
            {viewItem?.image_urls?.length > 0 ? (
              <>
                <ScrollView
                  horizontal
                  pagingEnabled
                  showsHorizontalScrollIndicator={false}
                  style={s.detailGallery}
                  onScroll={e => {
                    const idx = Math.round(e.nativeEvent.contentOffset.x / SCREEN_W);
                    setGalleryIndex(idx);
                  }}
                  scrollEventThrottle={16}
                >
                  {viewItem.image_urls.map((url, i) => (
                    <Image
                      key={i}
                      source={{ uri: url }}
                      style={[s.detailGalleryImg, { width: SCREEN_W }]}
                      resizeMode="cover"
                    />
                  ))}
                </ScrollView>
                {/* Dot indicators */}
                {viewItem.image_urls.length > 1 && (
                  <View style={s.dotRow}>
                    {viewItem.image_urls.map((_, i) => (
                      <View
                        key={i}
                        style={[s.dot, i === galleryIndex && s.dotActive]}
                      />
                    ))}
                  </View>
                )}
              </>
            ) : (
              <View style={s.detailGalleryEmpty}>
                <Text style={{ fontSize: 72 }}>{CAT_EMOJI[viewItem?.category] ?? '📦'}</Text>
              </View>
            )}

            <View style={{ padding: 16 }}>

              {/* Price row */}
              <View style={s.detailPriceRow}>
                <Text style={s.detailPrice}>₱{Number(viewItem?.price ?? 0).toLocaleString()}</Text>
                <View style={[s.condBadge, { backgroundColor: CONDITION_MAP[viewItem?.condition]?.bg ?? C.bg }]}>
                  <Text style={[s.condText, { color: CONDITION_MAP[viewItem?.condition]?.text ?? C.sub }]}>
                    {CONDITION_MAP[viewItem?.condition]?.label ?? viewItem?.condition}
                  </Text>
                </View>
              </View>

              {/* Status + stock row */}
              <View style={s.detailStatusRow}>
                <View style={[s.detailStatusBadge, { backgroundColor: STATUS_MAP[viewItem?.status]?.bg ?? C.green }]}>
                  <Text style={s.statusBadgeText}>{STATUS_MAP[viewItem?.status]?.label ?? 'AVAILABLE'}</Text>
                </View>
                <Text style={s.stockText}>{viewItem?.stock ?? 0} in stock</Text>
              </View>

              {/* Description */}
              <Text style={s.detailSectionLabel}>Description</Text>
              <Text style={s.detailDescription}>{viewItem?.description}</Text>

              {/* Location */}
              {viewItem?.location ? (
                <>
                  <Text style={s.detailSectionLabel}>Location</Text>
                  <Text style={s.detailMeta}>📍 {viewItem.location}</Text>
                </>
              ) : null}

              {/* Seller */}
              <Text style={s.detailSectionLabel}>Seller</Text>
              <View style={s.detailSellerRow}>
                <View style={s.detailSellerAvatar}>
                  <Text style={s.detailSellerAvatarText}>
                    {(viewItem?.seller?.name ?? 'S').charAt(0).toUpperCase()}
                  </Text>
                </View>
                <Text style={s.detailSellerName}>{viewItem?.seller?.name ?? 'School Marketplace'}</Text>
              </View>

              {/* Payment methods */}
              <Text style={s.detailSectionLabel}>Accepted Payments</Text>
              <View style={s.paymentRow}>
                {viewItem?.accepts_qrph  ? <Text style={s.paymentBadge}>QRPH</Text>  : null}
                {viewItem?.accepts_gcash ? <Text style={s.paymentBadge}>GCash</Text> : null}
                {viewItem?.accepts_cash  ? <Text style={s.paymentBadge}>Cash</Text>  : null}
              </View>

              {/* ── Action buttons ── */}
              <View style={s.detailActions}>
                {canBuyItems && viewItem?.status === 'available' && (
                  <TouchableOpacity
                    style={s.detailBuyBtn}
                    onPress={() => {
                      setViewItem(null);
                      openCheckout(viewItem);
                    }}
                  >
                    <Text style={s.buyBtnText}>🛒  Checkout</Text>
                  </TouchableOpacity>
                )}
                {canBuyItems && viewItem?.status !== 'available' && (
                  <View style={[s.detailBuyBtn, { backgroundColor: C.muted }]}>
                    <Text style={s.buyBtnText}>Unavailable</Text>
                  </View>
                )}
                {canManageListings && (
                  <TouchableOpacity
                    style={[s.detailEditBtn]}
                    onPress={() => {
                      setViewItem(null);
                      openEdit(viewItem);
                    }}
                  >
                    <Text style={[s.actionBtnText, { color: C.blue }]}>✏️  Edit listing</Text>
                  </TouchableOpacity>
                )}
              </View>

            </View>
          </ScrollView>
        </View>
      </Modal>

      {/* ── Sell Modal ── */}
      <Modal visible={showSell} animationType="slide" presentationStyle="pageSheet">
        <View style={s.modal}>
          <View style={s.modalHeader}>
            <Text style={s.modalTitle}>Post an item</Text>
            <TouchableOpacity style={s.modalCloseBtn} onPress={() => setShowSell(false)}>
              <Text style={s.modalCloseText}>✕</Text>
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={s.modalBody} keyboardShouldPersistTaps="handled">

            <Text style={s.fieldLabel}>Title *</Text>
            <TextInput style={s.fieldInput}
              placeholder="e.g. Science 10 Textbook"
              value={form.title}
              onChangeText={v => setForm(p => ({ ...p, title: v }))} />

            <Text style={s.fieldLabel}>Description *</Text>
            <TextInput style={[s.fieldInput, { height: 90, textAlignVertical: 'top' }]}
              placeholder="Describe your item — condition, what's included, etc."
              value={form.description}
              onChangeText={v => setForm(p => ({ ...p, description: v }))}
              multiline />

            <Text style={s.fieldLabel}>Price (₱) *</Text>
            <TextInput style={s.fieldInput}
              placeholder="0"
              value={form.price}
              onChangeText={v => setForm(p => ({ ...p, price: v }))}
              keyboardType="numeric" />

            <Text style={s.fieldLabel}>Stock *</Text>
            <TextInput style={s.fieldInput}
              placeholder="1"
              value={form.stock}
              onChangeText={v => setForm(p => ({ ...p, stock: v.replace(/[^0-9]/g, '') }))}
              keyboardType="number-pad" />

            <Text style={s.fieldLabel}>Category</Text>
            <View style={s.chipRow}>
              {['books', 'uniforms', 'electronics', 'supplies', 'other'].map(c => (
                <TouchableOpacity
                  key={c}
                  style={[s.chip, form.category === c && s.chipActive]}
                  onPress={() => setForm(p => ({ ...p, category: c }))}
                >
                  <Text style={[s.chipText, form.category === c && s.chipTextActive]}>
                    {CAT_EMOJI[c]}  {c}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={s.fieldLabel}>Condition</Text>
            <View style={s.chipRow}>
              {['new', 'like_new', 'good', 'fair'].map(c => (
                <TouchableOpacity
                  key={c}
                  style={[s.chip, form.condition === c && s.chipActive]}
                  onPress={() => setForm(p => ({ ...p, condition: c }))}
                >
                  <Text style={[s.chipText, form.condition === c && s.chipTextActive]}>
                    {c.replace('_', ' ')}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={s.fieldLabel}>Location</Text>
            <TextInput style={s.fieldInput}
              placeholder="e.g. Cebu City"
              value={form.location}
              onChangeText={v => setForm(p => ({ ...p, location: v }))} />

            <Text style={s.fieldLabel}>Item Photos (up to 3)</Text>
            <TouchableOpacity style={s.uploadBtn} onPress={() => pickItemImages(setItemImages)}>
              <Text style={s.uploadBtnText}>
                {itemImages.length > 0
                  ? `📷  ${itemImages.length} photo(s) selected — tap to change`
                  : '📷  Add item photos'}
              </Text>
            </TouchableOpacity>
            {itemImages.length > 0 ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
                <View style={{ flexDirection: 'row', gap: 8 }}>
                  {itemImages.map((img, i) => (
                    <Image key={i} source={{ uri: img.uri }} style={s.itemPhotoThumb} resizeMode="cover" />
                  ))}
                </View>
              </ScrollView>
            ) : (
              <Text style={s.helperText}>{"Optional but recommended - helps buyers see exactly what you're selling."}</Text>
            )}

            <Text style={s.fieldLabel}>Payment Methods</Text>
            <View style={s.chipRow}>
              <TouchableOpacity
                style={[s.chip, form.accepts_qrph && s.chipActive]}
                onPress={() => setForm(p => ({ ...p, accepts_qrph: !p.accepts_qrph }))}
              >
                <Text style={[s.chipText, form.accepts_qrph && s.chipTextActive]}>QRPH</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[s.chip, form.accepts_gcash && s.chipActive]}
                onPress={() => setForm(p => ({ ...p, accepts_gcash: !p.accepts_gcash }))}
              >
                <Text style={[s.chipText, form.accepts_gcash && s.chipTextActive]}>GCash online</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[s.chip, form.accepts_cash && s.chipActive]}
                onPress={() => setForm(p => ({ ...p, accepts_cash: !p.accepts_cash }))}
              >
                <Text style={[s.chipText, form.accepts_cash && s.chipTextActive]}>Cash</Text>
              </TouchableOpacity>
            </View>

            {form.accepts_gcash && (
              <View style={s.paymentPanel}>
                <Text style={s.fieldLabel}>GCash Account Name *</Text>
                <TextInput style={s.fieldInput}
                  placeholder="Account name"
                  value={form.gcash_name}
                  onChangeText={v => setForm(p => ({ ...p, gcash_name: v }))} />

                <Text style={s.fieldLabel}>GCash Number *</Text>
                <TextInput style={s.fieldInput}
                  placeholder="09XXXXXXXXX"
                  value={form.gcash_number}
                  onChangeText={v => setForm(p => ({ ...p, gcash_number: v }))}
                  keyboardType="phone-pad" />
              </View>
            )}

            {form.accepts_qrph && (
              <View style={s.paymentPanel}>
                <Text style={s.fieldLabel}>QRPH Image *</Text>
                <TouchableOpacity style={s.uploadBtn} onPress={pickQrphImage}>
                  <Text style={s.uploadBtnText}>{qrphImage ? 'Change QRPH image' : 'Upload QRPH image'}</Text>
                </TouchableOpacity>
                {qrphImage ? (
                  <Image source={{ uri: qrphImage.uri }} style={s.qrPreview} resizeMode="contain" />
                ) : form.qrph_image_url ? (
                  <Image source={{ uri: form.qrph_image_url }} style={s.qrPreview} resizeMode="contain" />
                ) : (
                  <Text style={s.helperText}>Select the QR image from your gallery. The app will upload it automatically.</Text>
                )}
              </View>
            )}

            <TouchableOpacity
              style={[s.postBtn, posting && { opacity: 0.6 }]}
              onPress={handleSell}
              disabled={posting}
            >
              {posting
                ? <ActivityIndicator color="#fff" />
                : <Text style={s.postBtnText}>Post item</Text>
              }
            </TouchableOpacity>

            <View style={{ height: 40 }} />
          </ScrollView>
        </View>
      </Modal>

      {/* ── Edit Modal ── */}
      <Modal visible={showEdit} animationType="slide" presentationStyle="pageSheet">
        <View style={s.modal}>
          <View style={s.modalHeader}>
            <Text style={s.modalTitle}>Edit listing</Text>
            <TouchableOpacity style={s.modalCloseBtn} onPress={() => setShowEdit(false)}>
              <Text style={s.modalCloseText}>✕</Text>
            </TouchableOpacity>
          </View>

          {editForm && (
            <ScrollView contentContainerStyle={s.modalBody} keyboardShouldPersistTaps="handled">

              <Text style={s.fieldLabel}>Title *</Text>
              <TextInput style={s.fieldInput}
                placeholder="e.g. Science 10 Textbook"
                value={editForm.title}
                onChangeText={v => setEditForm(p => ({ ...p, title: v }))} />

              <Text style={s.fieldLabel}>Description *</Text>
              <TextInput style={[s.fieldInput, { height: 90, textAlignVertical: 'top' }]}
                placeholder="Describe your item"
                value={editForm.description}
                onChangeText={v => setEditForm(p => ({ ...p, description: v }))}
                multiline />

              <Text style={s.fieldLabel}>Price (₱) *</Text>
              <TextInput style={s.fieldInput}
                placeholder="0"
                value={editForm.price}
                onChangeText={v => setEditForm(p => ({ ...p, price: v }))}
                keyboardType="numeric" />

              <Text style={s.fieldLabel}>Stock *</Text>
              <TextInput style={s.fieldInput}
                placeholder="1"
                value={editForm.stock}
                onChangeText={v => setEditForm(p => ({ ...p, stock: v.replace(/[^0-9]/g, '') }))}
                keyboardType="number-pad" />

              <Text style={s.fieldLabel}>Category</Text>
              <View style={s.chipRow}>
                {['books', 'uniforms', 'electronics', 'supplies', 'other'].map(c => (
                  <TouchableOpacity
                    key={c}
                    style={[s.chip, editForm.category === c && s.chipActive]}
                    onPress={() => setEditForm(p => ({ ...p, category: c }))}
                  >
                    <Text style={[s.chipText, editForm.category === c && s.chipTextActive]}>
                      {CAT_EMOJI[c]}  {c}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>

              <Text style={s.fieldLabel}>Condition</Text>
              <View style={s.chipRow}>
                {['new', 'like_new', 'good', 'fair'].map(c => (
                  <TouchableOpacity
                    key={c}
                    style={[s.chip, editForm.condition === c && s.chipActive]}
                    onPress={() => setEditForm(p => ({ ...p, condition: c }))}
                  >
                    <Text style={[s.chipText, editForm.condition === c && s.chipTextActive]}>
                      {c.replace('_', ' ')}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>

              <Text style={s.fieldLabel}>Location</Text>
              <TextInput style={s.fieldInput}
                placeholder="e.g. Cebu City"
                value={editForm.location}
                onChangeText={v => setEditForm(p => ({ ...p, location: v }))} />

              <Text style={s.fieldLabel}>Item Photos (up to 3)</Text>
              <TouchableOpacity style={s.uploadBtn} onPress={() => pickItemImages(setEditItemImages)}>
                <Text style={s.uploadBtnText}>
                  {editItemImages.length > 0
                    ? `📷  ${editItemImages.length} new photo(s) — tap to change`
                    : editItem?.image_urls?.length
                    ? `📷  ${editItem.image_urls.length} existing — tap to replace all`
                    : '📷  Add item photos'}
                </Text>
              </TouchableOpacity>
              {editItemImages.length > 0 ? (
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
                  <View style={{ flexDirection: 'row', gap: 8 }}>
                    {editItemImages.map((img, i) => (
                      <Image key={i} source={{ uri: img.uri }} style={s.itemPhotoThumb} resizeMode="cover" />
                    ))}
                  </View>
                </ScrollView>
              ) : editItem?.image_urls?.length ? (
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 10 }}>
                  <View style={{ flexDirection: 'row', gap: 8 }}>
                    {editItem.image_urls.map((url, i) => (
                      <Image key={i} source={{ uri: url }} style={s.itemPhotoThumb} resizeMode="cover" />
                    ))}
                  </View>
                </ScrollView>
              ) : (
                <Text style={s.helperText}>No photos yet. Tap above to add some.</Text>
              )}

              <Text style={s.fieldLabel}>Payment Methods</Text>
              <View style={s.chipRow}>
                <TouchableOpacity
                  style={[s.chip, editForm.accepts_qrph && s.chipActive]}
                  onPress={() => setEditForm(p => ({ ...p, accepts_qrph: !p.accepts_qrph }))}
                >
                  <Text style={[s.chipText, editForm.accepts_qrph && s.chipTextActive]}>QRPH</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[s.chip, editForm.accepts_gcash && s.chipActive]}
                  onPress={() => setEditForm(p => ({ ...p, accepts_gcash: !p.accepts_gcash }))}
                >
                  <Text style={[s.chipText, editForm.accepts_gcash && s.chipTextActive]}>GCash online</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[s.chip, editForm.accepts_cash && s.chipActive]}
                  onPress={() => setEditForm(p => ({ ...p, accepts_cash: !p.accepts_cash }))}
                >
                  <Text style={[s.chipText, editForm.accepts_cash && s.chipTextActive]}>Cash</Text>
                </TouchableOpacity>
              </View>

              {editForm.accepts_gcash && (
                <View style={s.paymentPanel}>
                  <Text style={s.fieldLabel}>GCash Account Name *</Text>
                  <TextInput style={s.fieldInput}
                    placeholder="Account name"
                    value={editForm.gcash_name}
                    onChangeText={v => setEditForm(p => ({ ...p, gcash_name: v }))} />

                  <Text style={s.fieldLabel}>GCash Number *</Text>
                  <TextInput style={s.fieldInput}
                    placeholder="09XXXXXXXXX"
                    value={editForm.gcash_number}
                    onChangeText={v => setEditForm(p => ({ ...p, gcash_number: v }))}
                    keyboardType="phone-pad" />
                </View>
              )}

              {editForm.accepts_qrph && (
                <View style={s.paymentPanel}>
                  <Text style={s.fieldLabel}>QRPH Image *</Text>
                  <TouchableOpacity style={s.uploadBtn} onPress={pickEditQrphImage}>
                    <Text style={s.uploadBtnText}>
                      {editQrphImage
                        ? 'Change QRPH image'
                        : editForm.qrph_image_url
                        ? 'Replace QRPH image'
                        : 'Upload QRPH image'}
                    </Text>
                  </TouchableOpacity>
                  {editQrphImage ? (
                    <Image source={{ uri: editQrphImage.uri }} style={s.qrPreview} resizeMode="contain" />
                  ) : editForm.qrph_image_url ? (
                    <Image source={{ uri: editForm.qrph_image_url }} style={s.qrPreview} resizeMode="contain" />
                  ) : (
                    <Text style={s.helperText}>Select the QR image from your gallery.</Text>
                  )}
                </View>
              )}

              <TouchableOpacity
                style={[s.postBtn, saving && { opacity: 0.6 }]}
                onPress={handleEdit}
                disabled={saving}
              >
                {saving
                  ? <ActivityIndicator color="#fff" />
                  : <Text style={s.postBtnText}>Save changes</Text>
                }
              </TouchableOpacity>

              <View style={{ height: 40 }} />
            </ScrollView>
          )}
        </View>
      </Modal>

      {/* ── Checkout Modal ── */}
      <Modal visible={!!checkoutItem} transparent animationType="fade">
        <View style={s.checkoutBackdrop}>
          <View style={s.checkoutCard}>
            <Text style={s.checkoutTitle}>Checkout</Text>

            {checkoutItem?.image_urls?.[0] && (
              <Image
                source={{ uri: checkoutItem.image_urls[0] }}
                style={s.checkoutItemImage}
                resizeMode="cover"
              />
            )}

            <Text style={s.checkoutItem}>{checkoutItem?.title}</Text>
            <Text style={s.checkoutPrice}>
              ₱{Number((checkoutItem?.price ?? 0) * (parseInt(checkoutQuantity, 10) || 1)).toLocaleString()}
            </Text>
            <Text style={s.checkoutItem}>₱{Number(checkoutItem?.price ?? 0).toLocaleString()} each · {checkoutItem?.stock ?? 1} in stock</Text>

            <Text style={s.fieldLabel}>Quantity</Text>
            <View style={s.quantityRow}>
              <TouchableOpacity
                style={s.quantityBtn}
                onPress={() => setCheckoutQuantity(String(Math.max(1, (parseInt(checkoutQuantity, 10) || 1) - 1)))}
              >
                <Text style={s.quantityBtnText}>-</Text>
              </TouchableOpacity>
              <TextInput
                style={[s.fieldInput, s.quantityInput]}
                value={checkoutQuantity}
                onChangeText={v => setCheckoutQuantity(v.replace(/[^0-9]/g, ''))}
                keyboardType="number-pad"
              />
              <TouchableOpacity
                style={s.quantityBtn}
                onPress={() => {
                  const current = parseInt(checkoutQuantity, 10) || 1;
                  setCheckoutQuantity(String(Math.min(checkoutItem?.stock ?? 1, current + 1)));
                }}
              >
                <Text style={s.quantityBtnText}>+</Text>
              </TouchableOpacity>
            </View>

            {paymentOptions?.qrph && (
              <View style={s.gcashBox}>
                <Text style={s.gcashTitle}>Pay with QRPH</Text>
                {(checkoutItem?.qrph_image_url || paymentOptions?.qrph?.image_url) ? (
                  <Image
                    source={{ uri: checkoutItem?.qrph_image_url || paymentOptions.qrph.image_url }}
                    style={s.qrImage}
                    resizeMode="contain"
                  />
                ) : null}
                <Text style={s.gcashLine}>Account: {paymentOptions?.qrph?.account_name}</Text>
                {paymentOptions?.qrph?.account_number ? (
                  <Text style={s.gcashLine}>Number: {paymentOptions.qrph.account_number}</Text>
                ) : null}
                <Text style={s.gcashLine}>{paymentOptions?.qrph?.instructions}</Text>
              </View>
            )}

            {checkoutItem?.accepts_gcash && (
              <View style={s.gcashBox}>
                <Text style={s.gcashTitle}>Pay with GCash</Text>
                <Text style={s.gcashLine}>Name: {checkoutItem?.gcash_name}</Text>
                <Text style={s.gcashLine}>Number: {checkoutItem?.gcash_number}</Text>
                <Text style={s.gcashLine}>Send payment, then enter the reference number below.</Text>
              </View>
            )}

            <Text style={s.fieldLabel}>Payment Method</Text>
            <View style={s.chipRow}>
              {checkoutItem?.accepts_qrph && paymentOptions?.qrph && (
                <TouchableOpacity
                  style={[s.chip, paymentMethod === 'qrph' && s.chipActive]}
                  onPress={() => setPaymentMethod('qrph')}
                >
                  <Text style={[s.chipText, paymentMethod === 'qrph' && s.chipTextActive]}>QRPH</Text>
                </TouchableOpacity>
              )}
              {checkoutItem?.accepts_gcash && (
                <TouchableOpacity
                  style={[s.chip, paymentMethod === 'gcash' && s.chipActive]}
                  onPress={() => setPaymentMethod('gcash')}
                >
                  <Text style={[s.chipText, paymentMethod === 'gcash' && s.chipTextActive]}>GCash online</Text>
                </TouchableOpacity>
              )}
              {checkoutItem?.accepts_cash && (
                <TouchableOpacity
                  style={[s.chip, paymentMethod === 'cash' && s.chipActive]}
                  onPress={() => setPaymentMethod('cash')}
                >
                  <Text style={[s.chipText, paymentMethod === 'cash' && s.chipTextActive]}>Cash</Text>
                </TouchableOpacity>
              )}
            </View>

            {['gcash', 'qrph'].includes(paymentMethod) && (
              <>
                <Text style={s.fieldLabel}>Payment Reference Number *</Text>
                <TextInput
                  style={s.fieldInput}
                  placeholder="Enter reference after payment"
                  value={paymentReference}
                  onChangeText={setPaymentReference}
                  autoCapitalize="characters"
                />
              </>
            )}

            <View style={s.checkoutActions}>
              <TouchableOpacity style={s.cancelBtn} onPress={() => setCheckoutItem(null)} disabled={!!buyingId}>
                <Text style={s.cancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[s.payBtn, buyingId && { opacity: 0.6 }]} onPress={handleBuy} disabled={!!buyingId}>
                {buyingId
                  ? <ActivityIndicator color="#fff" />
                  : <Text style={s.payBtnText}>{paymentMethod === 'qrph' ? 'Submit QRPH' : paymentMethod === 'gcash' ? 'Submit GCash' : 'Checkout'}</Text>
                }
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* ── Cancel Order Modal ── */}
      <Modal visible={!!cancelOrder} transparent animationType="fade">
        <View style={s.checkoutBackdrop}>
          <View style={s.checkoutCard}>
            <Text style={s.checkoutTitle}>Cancel checkout</Text>
            <Text style={s.checkoutItem}>{cancelOrder?.item?.title ?? 'Marketplace item'}</Text>
            <Text style={s.cancelPrompt}>Please tell the seller why you are cancelling.</Text>

            <TextInput
              style={[s.fieldInput, s.cancelReasonInput]}
              placeholder="Enter cancellation reason"
              value={cancelReason}
              onChangeText={setCancelReason}
              multiline
              textAlignVertical="top"
            />

            <View style={s.checkoutActions}>
              <TouchableOpacity
                style={s.cancelBtn}
                onPress={() => {
                  setCancelOrder(null);
                  setCancelReason('');
                }}
                disabled={!!cancellingId}
              >
                <Text style={s.cancelBtnText}>Keep checkout</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[s.confirmCancelBtn, cancellingId && { opacity: 0.6 }]}
                onPress={handleCancelOrder}
                disabled={!!cancellingId}
              >
                {cancellingId
                  ? <ActivityIndicator color="#fff" />
                  : <Text style={s.payBtnText}>Cancel</Text>
                }
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

// ── Styles ───────────────────────────────────────────────────────
const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: C.bg },
  center:    { flex: 1, justifyContent: 'center', alignItems: 'center' },

  header: {
    backgroundColor: C.blue,
    paddingTop: HEADER_TOP,
    paddingHorizontal: 16,
    paddingBottom: 14,
  },
  headerTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 12,
    gap: 12,
  },
  title:    { color: '#fff', fontSize: 22, fontWeight: '700' },
  subtitle: { color: 'rgba(255,255,255,0.75)', fontSize: 12, marginTop: 2 },
  sellBtn:  {
    backgroundColor: 'rgba(255,255,255,0.22)',
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 8,
    alignSelf: 'flex-start',
    marginTop: 2,
  },
  sellBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },

  toggleRow: {
    flexDirection: 'row',
    backgroundColor: 'rgba(0,0,0,0.18)',
    borderRadius: 12,
    padding: 3,
    marginBottom: 12,
    gap: 3,
  },
  toggleBtn:           { flex: 1, paddingVertical: 8, borderRadius: 10, alignItems: 'center' },
  toggleBtnActive:     { backgroundColor: '#fff' },
  toggleBtnText:       { fontSize: 13, color: 'rgba(255,255,255,0.8)', fontWeight: '600' },
  toggleBtnTextActive: { color: C.blue },

  searchRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  searchBox: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 9,
    gap: 8,
  },
  searchIcon:  { fontSize: 14 },
  searchInput: { flex: 1, color: '#fff', fontSize: 13 },

  catWrapper: {
    backgroundColor: C.card,
    borderBottomWidth: 1,
    borderBottomColor: C.border,
  },
  catScroll:      { paddingHorizontal: 12, paddingVertical: 8, gap: 6 },
  catTab:         {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    paddingHorizontal: 14, paddingVertical: 7,
    borderRadius: 20, backgroundColor: C.bg,
    borderWidth: 1, borderColor: C.border,
  },
  catTabActive:   { backgroundColor: C.blueLight, borderColor: C.blue },
  catEmoji:       { fontSize: 14 },
  catLabel:       { fontSize: 12, color: C.sub, fontWeight: '500' },
  catLabelActive: { color: C.blue, fontWeight: '700' },

  summaryBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: C.card,
    borderBottomWidth: 1,
    borderBottomColor: C.border,
    paddingVertical: 10,
    gap: 8,
  },
  summaryItem:    { fontSize: 13, color: C.sub },
  summaryCount:   { fontWeight: '700', color: C.text },
  summaryDivider: { color: C.muted, fontSize: 16 },

  grid: {
    padding: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    paddingBottom: 24,
  },
  itemCard: {
    width: '47.5%',
    backgroundColor: C.card,
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: C.border,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  },
  orderCard: {
    width: '100%',
    backgroundColor: C.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: C.border,
    padding: 14,
    shadowColor: '#000',
    shadowOpacity: 0.04,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  },
  orderTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  orderIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: C.blueLight,
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  orderThumb:    { width: 44, height: 44, borderRadius: 12 },
  orderIconText: { fontSize: 22 },
  orderTitle:   { fontSize: 14, color: C.text, fontWeight: '800' },
  orderSeller:  { fontSize: 12, color: C.sub, marginTop: 3 },
  orderStatus:  { borderRadius: 8, paddingHorizontal: 8, paddingVertical: 5 },
  orderStatusPaid:         { backgroundColor: C.greenLight },
  orderStatusReserved:     { backgroundColor: C.warningLight },
  orderStatusCancelled:    { backgroundColor: C.dangerLight },
  orderStatusText:         { fontSize: 10, fontWeight: '900' },
  orderStatusPaidText:     { color: C.green },
  orderStatusReservedText: { color: C.warning },
  orderStatusCancelledText:{ color: C.danger },
  orderMetaGrid:  { flexDirection: 'row', gap: 10, marginTop: 14 },
  orderMetaItem:  { flex: 1, backgroundColor: C.bg, borderRadius: 10, padding: 10 },
  orderMetaLabel: { fontSize: 11, color: C.muted, fontWeight: '700' },
  orderMetaValue: { fontSize: 14, color: C.text, fontWeight: '800', marginTop: 3 },
  referenceBox:   { marginTop: 12, backgroundColor: C.blueLight, borderRadius: 10, padding: 10 },
  cancelReasonBox:{ marginTop: 12, backgroundColor: C.dangerLight, borderRadius: 10, padding: 10 },
  referenceLabel: { fontSize: 11, color: C.blue, fontWeight: '800' },
  referenceValue: { fontSize: 14, color: C.text, fontWeight: '800', marginTop: 3 },
  orderNote:      { marginTop: 12, color: C.sub, fontSize: 12 },
  orderCancelBtn: {
    marginTop: 12,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
    backgroundColor: C.dangerLight,
    borderWidth: 1,
    borderColor: '#F4C7C7',
  },
  orderCancelText: { color: C.danger, fontWeight: '800', fontSize: 12 },
  verifyBtn: {
    marginTop: 12,
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    backgroundColor: C.green,
  },
  verifyBtnText: { color: '#fff', fontWeight: '800', fontSize: 12 },

  itemCardDimmed: { opacity: 0.72 },
  itemImg: {
    backgroundColor: '#F0F4FF',
    height: 120,
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  itemImgEmoji: { fontSize: 40 },

  statusBadge: {
    position: 'absolute',
    top: 8, left: 8,
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  statusBadgeText: { color: '#fff', fontSize: 10, fontWeight: '800', letterSpacing: 0.6 },

  photoCountBadge: {
    position: 'absolute',
    bottom: 8, right: 8,
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderRadius: 10,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  photoCountText: { color: '#fff', fontSize: 10, fontWeight: '700' },

  itemBody:      { padding: 10 },
  itemTitle:     { fontSize: 13, fontWeight: '600', color: C.text, marginBottom: 4, lineHeight: 18 },
  itemPrice:     { fontSize: 17, fontWeight: '800', color: C.blue, marginBottom: 8 },
  itemPriceSold: { textDecorationLine: 'line-through', color: C.muted, fontSize: 14, fontWeight: '500' },
  textDimmed:    { color: C.muted },

  itemMeta: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  condBadge:  { borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3 },
  condText:   { fontSize: 10, fontWeight: '600' },
  sellerName: { fontSize: 11, color: C.muted, maxWidth: '50%' },
  itemLocation: { fontSize: 11, color: C.muted, marginBottom: 6 },
  stockText:    { fontSize: 11, color: C.sub, marginBottom: 6, fontWeight: '600' },
  paymentRow:   { flexDirection: 'row', flexWrap: 'wrap', gap: 5, marginBottom: 8 },
  paymentBadge: {
    backgroundColor: C.blueLight,
    color: C.blue,
    fontSize: 10,
    fontWeight: '700',
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 10,
    overflow: 'hidden',
  },

  actionRow:        { gap: 6 },
  actionBtn:        { borderRadius: 8, paddingVertical: 7, alignItems: 'center', borderWidth: 1 },
  actionBtnText:    { fontSize: 11, fontWeight: '600' },
  actionBtnGreen:   { backgroundColor: C.greenLight,   borderColor: C.green   },
  actionBtnDanger:  { backgroundColor: C.dangerLight,  borderColor: C.danger  },
  actionBtnWarning: { backgroundColor: C.warningLight, borderColor: C.warning },
  actionBtnEdit:    { backgroundColor: C.blueLight,    borderColor: C.blue    },
  actionBtnDelete:  { backgroundColor: C.bg,           borderColor: C.border  },
  buyBtn:     { backgroundColor: C.green, borderRadius: 10, paddingVertical: 9, alignItems: 'center', marginTop: 2 },
  buyBtnText: { color: '#fff', fontSize: 12, fontWeight: '800' },

  emptyWrap:    { width: '100%', alignItems: 'center', paddingVertical: 60 },
  emptyIcon:    { fontSize: 52, marginBottom: 12 },
  emptyTitle:   { fontSize: 16, fontWeight: '600', color: C.text },
  emptySub:     { fontSize: 13, color: C.muted, marginTop: 4, textAlign: 'center', paddingHorizontal: 24 },
  emptyBtn:     { marginTop: 16, backgroundColor: C.blueLight, borderRadius: 20, paddingHorizontal: 20, paddingVertical: 10 },
  emptyBtnText: { color: C.blue, fontWeight: '600', fontSize: 13 },

  modal: { flex: 1, backgroundColor: C.card },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16, paddingTop: 52,
    borderBottomWidth: 1, borderBottomColor: C.border,
  },
  modalTitle:     { fontSize: 18, fontWeight: '700', color: C.text, flex: 1, marginRight: 12 },
  modalCloseBtn:  { padding: 4 },
  modalCloseText: { fontSize: 18, color: C.muted },
  modalBody:      { padding: 16 },
  fieldLabel:     { fontSize: 13, fontWeight: '600', color: C.sub, marginTop: 16, marginBottom: 6 },
  fieldInput: {
    borderWidth: 1, borderColor: C.border,
    borderRadius: 10, padding: 12,
    fontSize: 14, color: C.text, backgroundColor: C.bg,
  },
  helperText:   { fontSize: 12, color: C.muted, marginTop: 8 },
  paymentPanel: { backgroundColor: C.bg, borderRadius: 12, padding: 12, marginTop: 10 },
  chipRow:      { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip:         {
    paddingHorizontal: 14, paddingVertical: 7,
    borderRadius: 20, backgroundColor: C.bg,
    borderWidth: 1, borderColor: C.border,
  },
  chipActive:     { backgroundColor: C.blueLight, borderColor: C.blue },
  chipText:       { fontSize: 12, color: C.sub, fontWeight: '500' },
  chipTextActive: { color: C.blue, fontWeight: '700' },
  postBtn:        { backgroundColor: C.blue, borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 24 },
  postBtnText:    { color: '#fff', fontWeight: '700', fontSize: 15 },

  itemPhotoThumb: {
    width: 90,
    height: 90,
    borderRadius: 10,
    backgroundColor: C.bg,
    borderWidth: 1,
    borderColor: C.border,
  },

  // ── Item Detail Modal styles ──────────────────────────────────
  detailGallery:      { width: '100%', height: 280 },
  detailGalleryImg:   { height: 280 },
  detailGalleryEmpty: {
    height: 220,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F0F4FF',
  },
  dotRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
  },
  dot:       { width: 7, height: 7, borderRadius: 4, backgroundColor: C.muted },
  dotActive: { backgroundColor: C.blue, width: 18 },

  detailPriceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  detailPrice: { fontSize: 28, fontWeight: '800', color: C.blue },

  detailStatusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 16,
  },
  detailStatusBadge: {
    borderRadius: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },

  detailSectionLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: C.muted,
    marginTop: 16,
    marginBottom: 6,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  detailDescription: {
    fontSize: 14,
    color: C.text,
    lineHeight: 22,
  },
  detailMeta: { fontSize: 14, color: C.sub },

  detailSellerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  detailSellerAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: C.blueLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  detailSellerAvatarText: { fontSize: 15, fontWeight: '800', color: C.blue },
  detailSellerName:       { fontSize: 14, fontWeight: '600', color: C.text },

  detailActions: { marginTop: 24, gap: 10 },
  detailBuyBtn: {
    backgroundColor: C.green,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  detailEditBtn: {
    backgroundColor: C.blueLight,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: C.blue,
  },

  // ── Checkout modal ────────────────────────────────────────────
  checkoutBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.35)',
    justifyContent: 'center',
    padding: 18,
  },
  checkoutCard: {
    backgroundColor: C.card,
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: C.border,
  },
  checkoutTitle: { fontSize: 18, fontWeight: '800', color: C.text },
  checkoutItem:  { fontSize: 14, color: C.sub, marginTop: 6 },
  checkoutPrice: { fontSize: 24, fontWeight: '800', color: C.blue, marginTop: 8 },

  checkoutItemImage: {
    width: '100%',
    height: 160,
    borderRadius: 12,
    marginTop: 12,
    backgroundColor: C.bg,
  },

  quantityRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  quantityBtn: {
    width: 42, height: 42,
    borderRadius: 12,
    backgroundColor: C.blueLight,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#CFE3FA',
  },
  quantityBtnText: { color: C.blue, fontSize: 20, fontWeight: '900' },
  quantityInput:   { flex: 1, textAlign: 'center', fontWeight: '800' },
  gcashBox: {
    backgroundColor: C.blueLight,
    borderRadius: 12,
    padding: 12,
    marginTop: 14,
  },
  gcashTitle: { fontSize: 13, fontWeight: '800', color: C.blue, marginBottom: 6 },
  gcashLine:  { fontSize: 13, color: C.text, marginTop: 2 },
  qrImage:    { width: '100%', height: 220, backgroundColor: '#fff', borderRadius: 10, marginVertical: 10 },
  qrPreview:  { width: '100%', height: 160, backgroundColor: '#fff', borderRadius: 10, marginTop: 10 },
  uploadBtn:  { backgroundColor: C.blueLight, borderRadius: 10, paddingVertical: 12, alignItems: 'center', borderWidth: 1, borderColor: C.blue },
  uploadBtnText: { color: C.blue, fontSize: 13, fontWeight: '800' },
  cancelPrompt:      { color: C.sub, fontSize: 13, marginTop: 10, marginBottom: 12 },
  cancelReasonInput: { height: 96, textAlignVertical: 'top' },
  checkoutActions: { flexDirection: 'row', gap: 10, marginTop: 18 },
  cancelBtn: {
    flex: 1,
    backgroundColor: C.bg,
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: C.border,
  },
  cancelBtnText:    { color: C.sub, fontWeight: '700' },
  payBtn:           { flex: 1.3, backgroundColor: C.green,  borderRadius: 12, padding: 14, alignItems: 'center' },
  confirmCancelBtn: { flex: 1.1, backgroundColor: C.danger, borderRadius: 12, padding: 14, alignItems: 'center' },
  payBtnText:       { color: '#fff', fontWeight: '800' },
});

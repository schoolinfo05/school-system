// @ts-nocheck
// app/(tabs)/market.tsx — Marketplace screen with My Listings section

import { useEffect, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, ActivityIndicator,
  TouchableOpacity, TextInput, RefreshControl, Alert, Modal,
  Platform, StatusBar, Image,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../../src/api';

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
    if (role && !canManageListings && viewMode === 'mine') {
      setViewMode('browse');
    }
    if (role && !canManageListings && viewMode === 'sales') {
      setViewMode('browse');
    }
    if (role && !canBuyItems && viewMode === 'orders') {
      setViewMode('browse');
    }
  }, [role, canManageListings, canBuyItems, viewMode]);

  // ── Data fetching ───────────────────────────────────────────
  // Uses your index() — only returns status=available items
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

  // Uses your myItems() — returns ALL statuses for the logged-in user
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

  // ── Post item — uses your store() ───────────────────────────
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
    setPosting(true);
    try {
      await api.post('/marketplace', {
        ...form,
        price: parseFloat(form.price),
        stock: parseInt(form.stock, 10),
        gcash_name: form.accepts_gcash ? form.gcash_name.trim() : null,
        gcash_number: form.accepts_gcash ? form.gcash_number.trim() : null,
      });
      setShowSell(false);
      setForm({
        title: '', description: '', price: '', stock: '1',
        category: 'books', condition: 'good', location: 'Cebu City',
        accepts_cash: true, accepts_gcash: true, accepts_qrph: true,
        gcash_name: '', gcash_number: '', qrph_image_url: '',
      });
      if (viewMode === 'mine') fetchMyItems(); else fetchItems();
      Alert.alert('Listed!', 'Your item has been posted.');
    } catch {
      Alert.alert('Error', 'Could not post item. Please try again.');
    } finally {
      setPosting(false);
    }
  };

  // ── Update status — uses your update() via PUT /marketplace/{id}
  // Your update() accepts any of: title, description, price, category, condition, status, location
  const handleUpdateStatus = async (item, newStatus) => {
    setUpdatingId(item.id);
    try {
      await api.put(`/marketplace/${item.id}`, { status: newStatus });
      // Optimistic update — no need to refetch
      setMyItems(prev =>
        prev.map(i => i.id === item.id ? { ...i, status: newStatus } : i)
      );
    } catch {
      Alert.alert('Error', 'Could not update status. Please try again.');
    } finally {
      setUpdatingId(null);
    }
  };

  // ── Delete — uses your destroy() ───────────────────────────
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
        payment_method: paymentMethod,
        quantity,
        gcash_reference: ['gcash', 'qrph'].includes(paymentMethod) ? paymentReference.trim() : null,
      });
      const updated = res.data.item;
      const order = res.data.order;
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

  const renderOrderCard = (order) => {
    const item = order.item ?? {};
    const isGcash = order.payment_method === 'gcash';
    const isQrph = order.payment_method === 'qrph';
    const isCancelled = order.status === 'cancelled';
    const isCompleted = order.status === 'completed';
    const canCancel = !isCancelled && !isCompleted;

    return (
      <View key={order.id} style={s.orderCard}>
        <View style={s.orderTop}>
          <View style={s.orderIcon}>
            <Text style={s.orderIconText}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
          </View>
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
    const item = order.item ?? {};
    const buyerName = order.buyer?.name ?? 'Student buyer';
    const status = order.status ?? 'reserved';
    const isCancelled = status === 'cancelled';
    const isPaid = status === 'paid';
    const canVerify = ['pending_verification', 'reserved'].includes(status) && ['gcash', 'qrph'].includes(order.payment_method);

    return (
      <View key={order.id} style={s.orderCard}>
        <View style={s.orderTop}>
          <View style={s.orderIcon}>
            <Text style={s.orderIconText}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
          </View>
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

  // ── Render card (shared between browse + my listings) ───────
  const renderCard = (item, i) => {
    const cond          = CONDITION_MAP[item.condition] ?? CONDITION_MAP.good;
    const status        = STATUS_MAP[item.status]       ?? STATUS_MAP.available;
    const isSold        = item.status === 'sold';
    const isReserved    = item.status === 'reserved';
    const isUnavailable = isSold || isReserved;
    const isUpdating    = updatingId === item.id;
    const isBuying      = buyingId === item.id;
    const isMine        = viewMode === 'mine';

    return (
      <View key={i} style={[s.itemCard, isUnavailable && !isMine && s.itemCardDimmed]}>

        {/* Image area with status badge */}
        <View style={s.itemImg}>
          <Text style={s.itemImgEmoji}>{CAT_EMOJI[item.category] ?? '📦'}</Text>
          <View style={[s.statusBadge, { backgroundColor: status.bg }]}>
            <Text style={s.statusBadgeText}>{status.label}</Text>
          </View>
        </View>

        {/* Body */}
        <View style={s.itemBody}>
          <Text
            style={[s.itemTitle, isUnavailable && !isMine && s.textDimmed]}
            numberOfLines={2}
          >
            {item.title}
          </Text>

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

          {/* Location */}
          {item.location ? (
            <Text style={s.itemLocation} numberOfLines={1}>
              📍 {item.location}
            </Text>
          ) : null}
          <Text style={s.stockText}>{item.stock ?? 1} in stock</Text>
          <View style={s.paymentRow}>
            {item.accepts_qrph ? <Text style={s.paymentBadge}>QRPH</Text> : null}
            {item.accepts_gcash ? <Text style={s.paymentBadge}>GCash</Text> : null}
            {item.accepts_cash ? <Text style={s.paymentBadge}>Cash</Text> : null}
          </View>

          {/* ── My Listings: action buttons ── */}
          {isMine ? (
            <View style={s.actionRow}>
              {isUpdating ? (
                <ActivityIndicator size="small" color={C.blue} style={{ marginVertical: 8 }} />
              ) : (
                <>
                  {/* Relist — only show if currently unavailable */}
                  {isUnavailable && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnGreen]}
                      onPress={() => handleUpdateStatus(item, 'available')}
                    >
                      <Text style={[s.actionBtnText, { color: C.green }]}>✅ Relist</Text>
                    </TouchableOpacity>
                  )}

                  {/* Mark as sold — hide if already sold */}
                  {!isSold && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnDanger]}
                      onPress={() => handleUpdateStatus(item, 'sold')}
                    >
                      <Text style={[s.actionBtnText, { color: C.danger }]}>🚫 Mark Sold</Text>
                    </TouchableOpacity>
                  )}

                  {/* Mark as reserved — hide if already reserved */}
                  {!isReserved && (
                    <TouchableOpacity
                      style={[s.actionBtn, s.actionBtnWarning]}
                      onPress={() => handleUpdateStatus(item, 'reserved')}
                    >
                      <Text style={[s.actionBtnText, { color: C.warning }]}>🔒 Reserve</Text>
                    </TouchableOpacity>
                  )}

                  {/* Delete */}
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
              style={[s.buyBtn, isBuying && { opacity: 0.6 }]}
              onPress={() => openCheckout(item)}
              disabled={isBuying}
            >
              {isBuying
                ? <ActivityIndicator size="small" color="#fff" />
                : <Text style={s.buyBtnText}>Checkout</Text>
              }
            </TouchableOpacity>
          ) : null}
        </View>
      </View>
    );
  };

  // ── Render ───────────────────────────────────────────────────
  return (
    <View style={s.container}>

      {/* ── Header ── */}
      <View style={s.header}>
        <View style={s.headerTop}>
          <View style={{ flex: 1 }}>
            <Text style={s.title}>Marketplace</Text>
            <Text style={s.subtitle}>
              {viewMode === 'mine'
                ? 'Manage your listings'
                : viewMode === 'sales'
                ? 'Track buyers, payments, and stock'
                : viewMode === 'orders'
                ? 'Track reserved items and checkout payments'
                : 'Browse fixed-price school marketplace items'}
            </Text>
          </View>
          {canManageListings && (
            <TouchableOpacity style={s.sellBtn} onPress={() => setShowSell(true)}>
              <Text style={s.sellBtnText}>+ Sell</Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Browse / My Listings toggle */}
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

        {/* Search row — browse mode only */}
        {viewMode === 'browse' && (
          <View style={s.searchRow}>
            <View style={s.searchBox}>
              <Text style={s.searchIcon}>🔍</Text>
              <TextInput
                style={s.searchInput}
                placeholder="Search items..."
                placeholderTextColor="rgba(255,255,255,0.6)"
                value={search}
                onChangeText={setSearch}
                onSubmitEditing={fetchItems}
                returnKeyType="search"
              />
            </View>
          </View>
        )}
      </View>

      {/* ── Category tabs — browse mode only ── */}
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
            <Text style={[s.summaryCount, { color: C.danger }]}>
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
        <View style={s.center}>
          <ActivityIndicator size="large" color={C.blue} />
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
                <Text style={s.fieldLabel}>QRPH Image URL</Text>
                <TextInput style={s.fieldInput}
                  placeholder="https://.../school-qr.png"
                  value={form.qrph_image_url}
                  onChangeText={v => setForm(p => ({ ...p, qrph_image_url: v }))}
                  autoCapitalize="none" />
                {form.qrph_image_url ? (
                  <Image source={{ uri: form.qrph_image_url }} style={s.qrPreview} resizeMode="contain" />
                ) : (
                  <Text style={s.helperText}>Paste a QR image link here, or leave blank to use the school QRPH image.</Text>
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

      <Modal visible={!!checkoutItem} transparent animationType="fade">
        <View style={s.checkoutBackdrop}>
          <View style={s.checkoutCard}>
            <Text style={s.checkoutTitle}>Checkout</Text>
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
  },
  orderIconText: { fontSize: 22 },
  orderTitle: { fontSize: 14, color: C.text, fontWeight: '800' },
  orderSeller: { fontSize: 12, color: C.sub, marginTop: 3 },
  orderStatus: { borderRadius: 8, paddingHorizontal: 8, paddingVertical: 5 },
  orderStatusPaid: { backgroundColor: C.greenLight },
  orderStatusReserved: { backgroundColor: C.warningLight },
  orderStatusCancelled: { backgroundColor: C.dangerLight },
  orderStatusText: { fontSize: 10, fontWeight: '900' },
  orderStatusPaidText: { color: C.green },
  orderStatusReservedText: { color: C.warning },
  orderStatusCancelledText: { color: C.danger },
  orderMetaGrid: { flexDirection: 'row', gap: 10, marginTop: 14 },
  orderMetaItem: { flex: 1, backgroundColor: C.bg, borderRadius: 10, padding: 10 },
  orderMetaLabel: { fontSize: 11, color: C.muted, fontWeight: '700' },
  orderMetaValue: { fontSize: 14, color: C.text, fontWeight: '800', marginTop: 3 },
  referenceBox: { marginTop: 12, backgroundColor: C.blueLight, borderRadius: 10, padding: 10 },
  cancelReasonBox: { marginTop: 12, backgroundColor: C.dangerLight, borderRadius: 10, padding: 10 },
  referenceLabel: { fontSize: 11, color: C.blue, fontWeight: '800' },
  referenceValue: { fontSize: 14, color: C.text, fontWeight: '800', marginTop: 3 },
  orderNote: { marginTop: 12, color: C.sub, fontSize: 12 },
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
    height: 100,
    justifyContent: 'center',
    alignItems: 'center',
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
  stockText: { fontSize: 11, color: C.sub, marginBottom: 6, fontWeight: '600' },
  paymentRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 5, marginBottom: 8 },
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
  actionBtnDelete:  { backgroundColor: C.bg,           borderColor: C.border  },
  buyBtn:           { backgroundColor: C.green, borderRadius: 10, paddingVertical: 9, alignItems: 'center', marginTop: 2 },
  buyBtnText:       { color: '#fff', fontSize: 12, fontWeight: '800' },

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
  modalTitle:     { fontSize: 18, fontWeight: '700', color: C.text },
  modalCloseBtn:  { padding: 4 },
  modalCloseText: { fontSize: 18, color: C.muted },
  modalBody:      { padding: 16 },
  fieldLabel:     { fontSize: 13, fontWeight: '600', color: C.sub, marginTop: 16, marginBottom: 6 },
  fieldInput:     {
    borderWidth: 1, borderColor: C.border,
    borderRadius: 10, padding: 12,
    fontSize: 14, color: C.text, backgroundColor: C.bg,
  },
  paymentPanel:   { backgroundColor: C.bg, borderRadius: 12, padding: 12, marginTop: 10 },
  chipRow:        { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip:           {
    paddingHorizontal: 14, paddingVertical: 7,
    borderRadius: 20, backgroundColor: C.bg,
    borderWidth: 1, borderColor: C.border,
  },
  chipActive:     { backgroundColor: C.blueLight, borderColor: C.blue },
  chipText:       { fontSize: 12, color: C.sub, fontWeight: '500' },
  chipTextActive: { color: C.blue, fontWeight: '700' },
  postBtn:        { backgroundColor: C.blue, borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 24 },
  postBtnText:    { color: '#fff', fontWeight: '700', fontSize: 15 },
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
  checkoutItem: { fontSize: 14, color: C.sub, marginTop: 6 },
  checkoutPrice: { fontSize: 24, fontWeight: '800', color: C.blue, marginTop: 8 },
  quantityRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  quantityBtn: {
    width: 42,
    height: 42,
    borderRadius: 12,
    backgroundColor: C.blueLight,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#CFE3FA',
  },
  quantityBtnText: { color: C.blue, fontSize: 20, fontWeight: '900' },
  quantityInput: { flex: 1, textAlign: 'center', fontWeight: '800' },
  gcashBox: {
    backgroundColor: C.blueLight,
    borderRadius: 12,
    padding: 12,
    marginTop: 14,
  },
  gcashTitle: { fontSize: 13, fontWeight: '800', color: C.blue, marginBottom: 6 },
  gcashLine: { fontSize: 13, color: C.text, marginTop: 2 },
  qrImage: { width: '100%', height: 220, backgroundColor: '#fff', borderRadius: 10, marginVertical: 10 },
  qrPreview: { width: '100%', height: 160, backgroundColor: '#fff', borderRadius: 10, marginTop: 10 },
  cancelPrompt: { color: C.sub, fontSize: 13, marginTop: 10, marginBottom: 12 },
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
  cancelBtnText: { color: C.sub, fontWeight: '700' },
  payBtn: { flex: 1.3, backgroundColor: C.green, borderRadius: 12, padding: 14, alignItems: 'center' },
  confirmCancelBtn: { flex: 1.1, backgroundColor: C.danger, borderRadius: 12, padding: 14, alignItems: 'center' },
  payBtnText: { color: '#fff', fontWeight: '800' },
});

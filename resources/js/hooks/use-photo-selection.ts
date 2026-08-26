import { useCallback, useMemo, useState } from 'react';

export interface PhotoSelection {
    selectMode: boolean;
    selectedIds: number[];
    /** Selected photos whose fly-to-stack animation has finished. */
    landedIds: number[];
    count: number;
    allSelected: boolean;
    isSelected: (id: number) => boolean;
    enter: () => void;
    exit: () => void;
    toggle: (id: number) => void;
    markLanded: (id: number) => void;
    selectAll: () => void;
}

/**
 * Selection state for the gallery. Selection order is kept because the bottom
 * bar shows the last picked thumbnails, and "select all" only ever covers the
 * pictures already loaded on screen.
 */
export default function usePhotoSelection(loadedIds: number[]): PhotoSelection {
    const [selectMode, setSelectMode] = useState(false);
    const [order, setOrder] = useState<number[]>([]);
    const [landed, setLanded] = useState<number[]>([]);

    const reset = useCallback(() => {
        setOrder([]);
        setLanded([]);
    }, []);

    const enter = useCallback(() => setSelectMode(true), []);

    const exit = useCallback(() => {
        setSelectMode(false);
        reset();
    }, [reset]);

    const toggle = useCallback((id: number) => {
        setOrder((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));
        setLanded((current) => current.filter((x) => x !== id));
    }, []);

    const markLanded = useCallback((id: number) => {
        setLanded((current) => (current.includes(id) ? current : [...current, id]));
    }, []);

    const allSelected = useMemo(
        () => loadedIds.length > 0 && loadedIds.every((id) => order.includes(id)),
        [loadedIds, order],
    );

    const selectAll = useCallback(() => {
        if (allSelected) {
            reset();

            return;
        }

        setOrder(loadedIds);
        setLanded(loadedIds);
    }, [allSelected, loadedIds, reset]);

    const isSelected = useCallback((id: number) => order.includes(id), [order]);

    return {
        selectMode,
        selectedIds: order,
        landedIds: order.filter((id) => landed.includes(id)),
        count: order.length,
        allSelected,
        isSelected,
        enter,
        exit,
        toggle,
        markLanded,
        selectAll,
    };
}

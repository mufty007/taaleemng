/**
 * The landing page's demo broadsheet: switch term and watch totals, WAEC grades
 * and class positions recalculate.
 */
const roster = [
    { name: 'Chinedu Okafor', tint: '#FFE7B3', scores: [[34, 52], [36, 49], [35, 55]] },
    { name: 'Aisha Bello', tint: '#DCDDFD', scores: [[38, 55], [37, 57], [39, 56]] },
    { name: 'Tunde Adeyemi', tint: '#CDEBDD', scores: [[29, 41], [31, 45], [33, 47]] },
    { name: 'Ngozi Eze', tint: '#FFD6D2', scores: [[36, 50], [35, 54], [37, 51]] },
    { name: 'Musa Ibrahim', tint: '#D5E8FF', scores: [[27, 38], [30, 42], [32, 44]] },
];

const bands = [
    [75, 'A1'], [70, 'B2'], [65, 'B3'], [60, 'C4'], [55, 'C5'], [50, 'C6'], [45, 'D7'], [40, 'E8'], [0, 'F9'],
];

export const waecGrade = (total) => bands.find(([min]) => total >= min)[1];

export const ordinal = (n) => {
    const tens = n % 100;
    if (tens >= 11 && tens <= 13) {
        return `${n}th`;
    }

    return n + ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th');
};

export default () => ({
    term: 0,
    terms: ['1st Term', '2nd Term', '3rd Term'],

    get rows() {
        const rows = roster.map((student) => {
            const [ca, exam] = student.scores[this.term];

            return {
                ...student,
                initials: student.name.split(' ').map((word) => word[0]).join(''),
                ca,
                exam,
                total: ca + exam,
            };
        });

        // Competition ranking: equal totals share a position (1st, 2nd, 2nd, 4th).
        return rows.map((row) => {
            const grade = waecGrade(row.total);

            return {
                ...row,
                grade,
                gradeClass: grade.startsWith('A') || grade.startsWith('B')
                    ? 'bg-brand-50 text-brand-600'
                    : grade.startsWith('C') ? 'bg-gold-50 text-gold' : 'bg-danger-50 text-danger',
                position: ordinal(rows.filter((other) => other.total > row.total).length + 1),
            };
        });
    },
});
